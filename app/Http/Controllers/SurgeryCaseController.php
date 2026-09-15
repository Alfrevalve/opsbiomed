<?php

namespace App\Http\Controllers;

use App\Enums\CaseStatus;
use App\Http\Requests\AssignSurgeryCaseInstrumentistRequest;
use App\Http\Requests\CloseSurgeryCaseRequest;
use App\Http\Requests\StoreCasePreparationRequest;
use App\Http\Requests\StoreCaseResourceAssignmentRequest;
use App\Http\Requests\StoreReservationRequest;
use App\Http\Requests\StoreSurgeryCaseRequest;
use App\Http\Requests\TransitionSurgeryCaseRequest;
use App\Http\Requests\UpdateSurgeryCaseRequest;
use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\BillingRecord;
use App\Models\CaseMaterialSent;
use App\Models\CaseMaterialUsed;
use App\Models\CasePreparation;
use App\Models\CaseReconciliation;
use App\Models\CaseResourceAssignment;
use App\Models\CaseReturn;
use App\Models\CaseValuation;
use App\Models\Doctor;
use App\Models\DocumentEvidence;
use App\Models\Failure;
use App\Models\Institution;
use App\Models\InventoryLot;
use App\Models\OperationalAlert;
use App\Models\Patient;
use App\Models\Reservation;
use App\Models\SurgeryCase;
use App\Models\SurgeryType;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Inventory\ReservationService;
use App\Services\Inventory\StockRiskService;
use App\Services\Operations\ProductPriceService;
use App\Services\Operations\SurgeryCaseClosingService;
use App\Services\Operations\SurgeryCasePreparationService;
use App\Services\Operations\SurgeryCaseTransitionService;
use App\Services\Operations\SurgeryScheduleService;
use App\Services\Traceability\TraceCodeService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class SurgeryCaseController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', SurgeryCase::class);

        return view('cases.index', [
            'cases' => SurgeryCase::query()
                ->with(['institution', 'doctor', 'patient', 'surgeryType'])
                ->orderBy('scheduled_at')
                ->paginate(25),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', SurgeryCase::class);

        return view('cases.create', [
            'institutions' => Institution::query()->where('active', true)->orderBy('name')->get(),
            'doctors' => Doctor::query()->where('active', true)->orderBy('name')->get(),
            'surgeryTypes' => SurgeryType::query()->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreSurgeryCaseRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $request->validated();

        $case = DB::transaction(function () use ($data, $request): SurgeryCase {
            $patient = Patient::firstOrCreate(
                ['full_name' => $data['patient_name']],
                ['code' => 'PAT-'.Str::upper(Str::random(10))],
            );

            return SurgeryCase::create([
                'case_code' => 'MR8-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(4)),
                'status' => CaseStatus::SolicitudRegistrada,
                'institution_id' => $data['institution_id'],
                'doctor_id' => $data['doctor_id'],
                'patient_id' => $patient->id,
                'surgery_type_id' => $data['surgery_type_id'],
                'scheduled_at' => $data['scheduled_at'],
                'priority' => $data['priority'],
                'procedure_name' => $data['material_requested'],
                'request_origin' => $data['request_origin'],
                'commercial_condition' => $data['commercial_condition'] ?? null,
                'notes' => $data['notes'],
                'created_by' => $request->user()->id,
            ]);
        });

        $auditLogger->record('case.created', $case, [], [
            'case_code' => $case->case_code,
            'status' => $case->status->value,
        ]);

        return redirect()->route('cases.show', $case)->with('status', 'Solicitud quirurgica registrada.');
    }

    public function edit(SurgeryCase $case): View|RedirectResponse
    {
        $this->authorize('update', $case);

        if (! $case->status->isEditable()) {
            return redirect()->route('cases.show', $case)
                ->with('error', 'La solicitud no permite edicion normal en su estado actual.');
        }

        $case->load(['institution', 'doctor', 'patient', 'surgeryType']);

        return view('cases.edit', [
            'case' => $case,
            'editableFields' => $case->status->editableFields(),
            'institutions' => Institution::query()->where('active', true)->orderBy('name')->get(),
            'doctors' => Doctor::query()->where('active', true)->orderBy('name')->get(),
            'surgeryTypes' => SurgeryType::query()->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateSurgeryCaseRequest $request, SurgeryCase $case, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorize('update', $case);

        if (! $case->status->isEditable()) {
            return redirect()->route('cases.show', $case)
                ->with('error', 'La solicitud no permite edicion normal en su estado actual.');
        }

        [$case, $before, $after] = DB::transaction(function () use ($request, $case): array {
            $validated = $request->validated();
            $editableFields = $case->status->editableFields();
            $attributes = [];

            foreach (['institution_id', 'doctor_id', 'surgery_type_id', 'scheduled_at', 'request_origin', 'notes'] as $field) {
                if (in_array($field, $editableFields, true) && array_key_exists($field, $validated)) {
                    $attributes[$field] = $validated[$field];
                }
            }

            if (in_array('material_requested', $editableFields, true)) {
                $attributes['procedure_name'] = $validated['material_requested'];
            }

            if (in_array('patient_name', $editableFields, true)) {
                $patient = Patient::firstOrCreate(
                    ['full_name' => $validated['patient_name']],
                    ['code' => 'PAT-'.Str::upper(Str::random(10))],
                );
                $attributes['patient_id'] = $patient->id;
            }

            $before = collect(array_keys($attributes))
                ->mapWithKeys(fn (string $field): array => [$field => $case->getRawOriginal($field)])
                ->all();

            $case->fill($attributes);
            $case->save();

            $after = collect(array_keys($attributes))
                ->mapWithKeys(fn (string $field): array => [$field => $case->getRawOriginal($field)])
                ->all();

            return [$case, $before, $after];
        });

        $auditLogger->record('case.updated', $case, $before, $after);

        return redirect()->route('cases.show', $case)->with('status', 'Solicitud actualizada correctamente.');
    }

    public function show(SurgeryCase $case, StockRiskService $stockRiskService): View
    {
        $this->authorize('view', $case);

        $case->load([
            'institution',
            'doctor',
            'patient',
            'surgeryType',
            'createdBy',
            'reservations.reservedBy',
            'reservations.inventoryLot.product',
            'reservations.inventoryLot.warehouse',
            'materialsUsed.inventoryLot.product',
            'returns.inventoryLot.product',
            'returns.inspectedBy',
            'returns.inspectionResponsible',
            'returns.technicalFailure',
            'valuation.lines.product',
            'billingRecord',
            'reconciliation',
            'failures.inventoryLot.product',
            'failures.reportedBy',
            'failures.responsibleTechnical',
            'documents.uploadedBy',
            'documents.validatedBy',
        ]);
        $reservationOverview = $stockRiskService->caseOverview($case);
        $canViewBilling = auth()->user()?->can('billing.view') ?? false;
        $canAudit = auth()->user()?->can('audit.view') ?? false;
        $auditLogs = $canAudit
            ? AuditLog::query()
                ->with('user')
                ->where('auditable_type', SurgeryCase::class)
                ->where('auditable_id', $case->id)
                ->latest('created_at')
                ->get()
            : collect();

        return view('cases.show', compact('case', 'reservationOverview', 'canViewBilling', 'canAudit', 'auditLogs'));
    }

    public function control(
        SurgeryCase $case,
        StockRiskService $stockRiskService,
        SurgeryCaseTransitionService $transitionService,
        SurgeryCasePreparationService $preparationService,
        SurgeryScheduleService $scheduleService,
    ): View {
        $this->authorize('view', $case);

        $case->load([
            'institution',
            'doctor',
            'patient',
            'surgeryType.kitRules',
            'createdBy',
            'assignedInstrumentist',
            'assignedBy',
            'resourceAssignments.inventoryLot.product',
            'resourceAssignments.inventoryLot.warehouse',
            'resourceAssignments.assignedBy',
            'reservations.reservedBy',
            'reservations.inventoryLot.product',
            'reservations.inventoryLot.warehouse',
            'preparation.preparedBy',
            'preparation.dispatchedBy',
            'materialsSent.reservation',
            'materialsSent.inventoryLot.product',
            'materialsSent.inventoryLot.warehouse',
            'materialsSent.sentBy',
            'materialsUsed.reservation',
            'materialsUsed.inventoryLot.product',
            'materialsUsed.inventoryLot.warehouse',
            'returns.inventoryLot.product',
            'returns.inventoryLot.warehouse',
            'returns.inspectedBy',
            'returns.inspectionResponsible',
            'returns.technicalFailure',
            'failures.product',
            'failures.inventoryLot.product',
            'failures.inventoryLot.warehouse',
            'failures.reportedBy',
            'failures.responsibleTechnical',
            'documents.uploadedBy',
            'documents.validatedBy',
            'valuation.lines.product',
            'valuation.approval.requestedBy',
            'valuation.approval.approvedBy',
            'billingRecord',
            'reconciliation',
        ]);

        $user = auth()->user();
        $canViewBilling = $user?->can('billing.view') ?? false;
        $canViewDocuments = $user?->can('documents.view') ?? false;
        $canViewFailures = $user?->can('failures.view') ?? false;
        $canViewReturns = $user?->can('returns.view') ?? false;
        $canViewInventory = $user?->can('inventory.view') ?? false;
        $canAudit = $user?->can('audit.view') ?? false;
        $canViewSchedule = $user?->can('schedule.view') ?? false;
        $canViewAlerts = $user?->can('alerts.view') ?? false;
        $canManageSchedule = $user?->can('schedule.manage') ?? false;
        $canPrepare = $user?->can('prepare', $case) ?? false;
        $canTransition = $user?->can('transition', $case) ?? false;
        $canOverrideTransition = $canTransition
            && $user instanceof User
            && $transitionService->canOverride($user);
        $transitionOptions = $canTransition && $user instanceof User
            ? $transitionService->availableTransitions($case, $user)
            : collect();
        $transitionNotice = $transitionService->nextStepMessage($case);
        $statusTransitions = $transitionService->recentTransitions($case);
        $preparationSummary = $preparationService->summary($case);
        $scheduleContext = $canViewSchedule ? $scheduleService->caseContext($case) : [];
        $scheduleConflicts = $canViewSchedule ? $scheduleService->conflictsForCase($case) : collect();
        $instrumentists = $canManageSchedule ? $scheduleService->availableInstrumentists() : collect();
        $availableResources = $canManageSchedule ? $scheduleService->availableResources() : collect();
        $resourceTypeLabels = SurgeryScheduleService::resourceTypeLabels();

        $reservationOverview = $stockRiskService->caseOverview($case);
        $reservationIncomplete = $reservationOverview['risks']->isNotEmpty()
            && ! $reservationOverview['complete'];
        $pendingDocuments = $case->documents->whereIn('status', ['pendiente', 'cargado']);
        $openFailures = $case->failures->whereIn('status', [
            'reportada',
            'bloqueada',
            'en_revision',
            'pendiente_repuesto',
        ]);
        $pendingReturns = $case->returns->where('condition', 'pendiente_inspeccion');
        $unreconciledDifferences = $case->materialsUsed
            ->filter(fn (CaseMaterialUsed $material): bool => (int) $material->difference_qty > 0);
        $billing = $case->billingRecord;
        $hasPendingCostZero = $case->valuation?->approval?->status === 'pendiente_aprobacion'
            || $billing?->invoice_status === 'costo_cero_pendiente_aprobacion';

        $controlAlerts = $this->controlAlerts(
            $case,
            $reservationIncomplete,
            $unreconciledDifferences->count(),
            $pendingDocuments->count(),
            $openFailures->count(),
            $pendingReturns->count(),
            $hasPendingCostZero,
            $billing,
            $canViewBilling,
            $canViewDocuments,
            $canViewFailures,
            $canViewReturns,
        );
        if ($preparationSummary['required'] && ! $preparationSummary['ready_for_room']) {
            $controlAlerts->prepend([
                'level' => 'warning',
                'title' => 'Preoperatorio y despacho pendiente',
                'description' => 'Falta completar el checklist, el despacho exacto de reservas o la guia/evidencia.',
                'href' => $canPrepare ? route('cases.preparation', $case) : null,
            ]);
        }
        if ($canViewAlerts) {
            OperationalAlert::query()
                ->where('alertable_type', SurgeryCase::class)
                ->where('alertable_id', $case->id)
                ->whereIn('status', array_merge(OperationalAlert::OPEN_STATUSES, ['expired']))
                ->latest('detected_at')
                ->get()
                ->each(function (OperationalAlert $alert) use ($controlAlerts): void {
                    $controlAlerts->prepend([
                        'level' => in_array($alert->priority, ['critical', 'high'], true) ? 'danger' : 'warning',
                        'title' => 'SLA: '.$alert->title,
                        'description' => $alert->description,
                        'href' => route('alerts.show', $alert),
                    ]);
                });
        }
        foreach ($scheduleConflicts->take(3)->reverse() as $conflict) {
            $controlAlerts->prepend([
                'level' => $conflict['severity'] === 'critico' ? 'danger' : 'warning',
                'title' => 'Alerta de agenda: '.Str::headline($conflict['type']),
                'description' => $conflict['suggestion'],
                'href' => route('schedule.conflicts'),
            ]);
        }
        $timeline = $transitionService->timeline($case);
        $materialRows = $this->controlMaterialRows($case);
        $auditEntries = $canAudit
            ? $this->caseAuditLogs($case)
                ->map(fn (AuditLog $audit): array => [
                    'action' => $audit->action,
                    'user' => $audit->user?->name ?: 'Sistema',
                    'created_at' => $audit->created_at,
                    'summary' => $this->auditSummary($audit),
                ])
                ->values()
            : collect();

        return view('cases.control', compact(
            'case',
            'reservationOverview',
            'controlAlerts',
            'timeline',
            'materialRows',
            'pendingDocuments',
            'openFailures',
            'pendingReturns',
            'billing',
            'canViewBilling',
            'canViewDocuments',
            'canViewFailures',
            'canViewReturns',
            'canViewInventory',
            'canAudit',
            'canViewSchedule',
            'canViewAlerts',
            'canManageSchedule',
            'canPrepare',
            'preparationSummary',
            'scheduleContext',
            'scheduleConflicts',
            'instrumentists',
            'availableResources',
            'resourceTypeLabels',
            'auditEntries',
            'canTransition',
            'canOverrideTransition',
            'transitionOptions',
            'transitionNotice',
            'statusTransitions',
        ));
    }

    public function transition(
        TransitionSurgeryCaseRequest $request,
        SurgeryCase $case,
        SurgeryCaseTransitionService $transitionService,
    ): RedirectResponse {
        $this->authorize('transition', $case);
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        try {
            $transitionService->transition($case, $request->validated(), $user);
        } catch (DomainException $exception) {
            return redirect()
                ->route('cases.control', $case)
                ->withErrors(['transition' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('cases.control', $case)
            ->with('status', 'Estado operativo actualizado y auditado.');
    }

    public function assignInstrumentist(
        AssignSurgeryCaseInstrumentistRequest $request,
        SurgeryCase $case,
        SurgeryScheduleService $scheduleService,
    ): RedirectResponse {
        $this->authorize('schedule', $case);
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        try {
            $instrumentistId = $request->validated('assigned_instrumentist_id');
            $scheduleService->assignInstrumentist(
                $case,
                $instrumentistId === null ? null : (int) $instrumentistId,
                $user,
            );
        } catch (DomainException $exception) {
            return redirect()
                ->route('cases.control', $case)
                ->withErrors(['assigned_instrumentist_id' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('cases.control', $case)
            ->with('status', 'Instrumentista actualizado y auditado.');
    }

    public function assignResource(
        StoreCaseResourceAssignmentRequest $request,
        SurgeryCase $case,
        SurgeryScheduleService $scheduleService,
    ): RedirectResponse {
        $this->authorize('schedule', $case);
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        try {
            $scheduleService->assignResource($case, $request->validated(), $user);
        } catch (DomainException $exception) {
            return redirect()
                ->route('cases.control', $case)
                ->withErrors(['resource_assignment' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('cases.control', $case)
            ->with('status', 'Recurso asignado y auditado.');
    }

    public function unassignResource(
        SurgeryCase $case,
        CaseResourceAssignment $assignment,
        SurgeryScheduleService $scheduleService,
    ): RedirectResponse {
        $this->authorize('schedule', $case);
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        try {
            $scheduleService->unassignResource($case, $assignment, $user);
        } catch (DomainException $exception) {
            return redirect()
                ->route('cases.control', $case)
                ->withErrors(['resource_assignment' => $exception->getMessage()]);
        }

        return redirect()
            ->route('cases.control', $case)
            ->with('status', 'Recurso retirado de la agenda y auditado.');
    }

    public function preparationForm(
        SurgeryCase $case,
        SurgeryCasePreparationService $preparationService,
    ): View|RedirectResponse {
        $this->authorize('prepare', $case);

        $case->load([
            'institution',
            'doctor',
            'patient',
            'surgeryType',
            'preparation.preparedBy',
            'preparation.dispatchedBy',
            'reservations.inventoryLot.product',
            'reservations.inventoryLot.warehouse',
            'materialsSent.reservation',
            'materialsSent.inventoryLot.product',
            'materialsSent.sentBy',
        ]);
        $preparationSummary = $preparationService->summary($case);

        if (! $preparationSummary['required']) {
            return redirect()
                ->route('cases.control', $case)
                ->with('error', 'El caso no se encuentra en una etapa que permita preparar o despachar material.');
        }

        return view('cases.preparation', compact('case', 'preparationSummary'));
    }

    public function prepare(
        StoreCasePreparationRequest $request,
        SurgeryCase $case,
        SurgeryCasePreparationService $preparationService,
        SurgeryCaseTransitionService $transitionService,
    ): RedirectResponse {
        $this->authorize('prepare', $case);
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        try {
            $preparationService->prepareAndDispatch($case, $request->validated(), $user);
            $case->refresh();

            if (in_array($case->status, [CaseStatus::Reservado, CaseStatus::Reservada], true)) {
                $transitionService->transition($case, [
                    'target_status' => CaseStatus::Preparacion->value,
                    'observation' => 'Checklist preoperatorio y despacho de material confirmados.',
                    'override' => false,
                ], $user);
            }
        } catch (DomainException $exception) {
            return redirect()
                ->route('cases.preparation', $case)
                ->withErrors(['preparation' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('cases.control', $case)
            ->with('status', 'Preparacion preoperatoria y despacho registrados correctamente.');
    }

    public function reserveForm(SurgeryCase $case, StockRiskService $stockRiskService): View
    {
        $this->authorize('reserve', $case);

        $case->load(['institution', 'doctor', 'patient', 'surgeryType']);
        $reservationOverview = $stockRiskService->caseOverview($case);

        return view('cases.reserve', compact('case', 'reservationOverview'));
    }

    public function reserve(
        StoreReservationRequest $request,
        SurgeryCase $case,
        ReservationService $reservationService,
        TraceCodeService $traceCodeService,
    ): RedirectResponse {
        $this->authorize('reserve', $case);

        try {
            $reservation = $reservationService->reserveLot(
                $case,
                (int) $request->validated('inventory_lot_id'),
                (int) $request->validated('quantity'),
            );

            if (filled($request->validated('inventory_lot_trace_code'))) {
                $traceCodeService->record('scanned', $reservation->inventoryLot, $case->id, [
                    'source' => 'case_reservation',
                    'reservation_id' => $reservation->id,
                ]);
            }
        } catch (RuntimeException $exception) {
            return redirect()->route('cases.reserve.create', $case)
                ->withErrors(['quantity' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()->route('cases.show', $case)->with('status', 'Reserva creada correctamente.');
    }

    public function closeForm(SurgeryCase $case, ProductPriceService $priceService, Request $request): View|RedirectResponse
    {
        $this->authorize('close', $case);

        if (in_array($case->status, [CaseStatus::Cerrado, CaseStatus::Cerrada, CaseStatus::Facturada], true)) {
            return redirect()->route('cases.show', $case)
                ->with('error', 'La cirugia ya fue cerrada.');
        }

        $case->load([
            'institution',
            'doctor',
            'patient',
            'surgeryType',
            'reservations' => fn ($query) => $query
                ->where('status', 'active')
                ->with(['inventoryLot.product', 'inventoryLot.warehouse'])
                ->orderBy('id'),
            'documents.uploadedBy',
            'documents.validatedBy',
        ]);

        if ($case->reservations->isEmpty()) {
            return redirect()->route('cases.show', $case)
                ->with('error', 'No hay reservas activas que conciliar para cerrar la cirugia.');
        }

        $priceSuggestions = $case->reservations->mapWithKeys(function ($reservation) use ($case, $priceService): array {
            $price = $priceService->current(
                $reservation->inventoryLot->product_id,
                $case->institution_id,
                $case->doctor_id,
            );

            return [$reservation->id => $price];
        });
        $canOverridePrice = $priceService->canManuallyOverride($request->user());

        return view('cases.close', compact('case', 'priceSuggestions', 'canOverridePrice'));
    }

    public function close(
        CloseSurgeryCaseRequest $request,
        SurgeryCase $case,
        SurgeryCaseClosingService $closingService,
    ): RedirectResponse {
        $this->authorize('close', $case);

        try {
            $closingService->close($case, $request->validated(), (int) $request->user()->id);
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['close' => $exception->getMessage()]);
        }

        return redirect()
            ->route('cases.show', $case)
            ->with('status', 'Cirugia cerrada, consumo registrado y valorizacion preliminar creada.');
    }

    /**
     * @return Collection<int, array{level: string, title: string, description: string, href: ?string}>
     */
    private function controlAlerts(
        SurgeryCase $case,
        bool $reservationIncomplete,
        int $unreconciledDifferences,
        int $pendingDocuments,
        int $openFailures,
        int $pendingReturns,
        bool $hasPendingCostZero,
        ?BillingRecord $billing,
        bool $canViewBilling,
        bool $canViewDocuments,
        bool $canViewFailures,
        bool $canViewReturns,
    ): Collection {
        $alerts = collect();
        $firstOpenFailure = $case->failures->first(
            fn (Failure $failure): bool => in_array($failure->status, [
                'reportada',
                'bloqueada',
                'en_revision',
                'pendiente_repuesto',
            ], true),
        );
        $firstPendingReturn = $case->returns->firstWhere('condition', 'pendiente_inspeccion');

        if ($reservationIncomplete) {
            $alerts->push([
                'level' => 'danger',
                'title' => 'Reserva incompleta',
                'description' => 'Faltan combinaciones requeridas para atender la cirugia.',
                'href' => $case->status->allowsReservation() && (auth()->user()?->can('reservations.create') ?? false)
                    ? route('cases.reserve.create', $case)
                    : null,
            ]);
        }

        if ($case->status === CaseStatus::PendienteCierre) {
            $alerts->push([
                'level' => 'warning',
                'title' => 'Pendiente de cierre',
                'description' => 'La cirugia requiere registrar consumo, devoluciones y evidencia.',
                'href' => auth()->user()?->can('close', $case) ?? false
                    ? route('cases.close.create', $case)
                    : null,
            ]);
        }

        if ($unreconciledDifferences > 0) {
            $alerts->push([
                'level' => 'warning',
                'title' => 'Diferencia sin conciliar',
                'description' => "Hay {$unreconciledDifferences} lote(s) con diferencia de consumo pendiente.",
                'href' => (
                    auth()->user()?->can('cases.close')
                    || auth()->user()?->can('billing.view')
                    || auth()->user()?->can('billing.update')
                )
                    ? route('cases.reconciliation', $case)
                    : null,
            ]);
        }

        if ($canViewDocuments && $pendingDocuments > 0) {
            $alerts->push([
                'level' => 'warning',
                'title' => 'Documento pendiente de validar',
                'description' => "Hay {$pendingDocuments} documento(s) cargado(s) sin validacion.",
                'href' => route('cases.documents.index', $case),
            ]);
        }

        if ($canViewFailures && $openFailures > 0) {
            $alerts->push([
                'level' => 'danger',
                'title' => 'Falla abierta',
                'description' => "Hay {$openFailures} falla(s) tecnica(s) en seguimiento.",
                'href' => $firstOpenFailure ? route('failures.show', $firstOpenFailure) : null,
            ]);
        }

        if ($canViewReturns && $pendingReturns > 0) {
            $alerts->push([
                'level' => 'warning',
                'title' => 'Devolucion pendiente de inspeccion',
                'description' => "Hay {$pendingReturns} devolucion(es) por inspeccionar.",
                'href' => $firstPendingReturn ? route('returns.show', $firstPendingReturn) : null,
            ]);
        }

        if ($canViewBilling && $hasPendingCostZero) {
            $alerts->push([
                'level' => 'warning',
                'title' => 'Costo cero pendiente de aprobacion',
                'description' => 'La valorizacion requiere aprobacion antes de continuar con facturacion.',
                'href' => route('approvals.cost-zero.index'),
            ]);
        }

        if ($canViewBilling && $billing?->invoice_status === 'pendiente_oc' && blank($billing->purchase_order)) {
            $alerts->push([
                'level' => 'warning',
                'title' => 'OC pendiente',
                'description' => 'El caso valorizado aun no tiene orden de compra registrada.',
                'href' => route('billing.show', $case),
            ]);
        }

        if ($canViewBilling
            && in_array($billing?->invoice_status, ['valorizado', 'pendiente_factura'], true)
            && blank($billing?->invoice_number)) {
            $alerts->push([
                'level' => 'warning',
                'title' => 'Factura pendiente',
                'description' => 'El caso valorizado aun no tiene numero de factura.',
                'href' => route('billing.show', $case),
            ]);
        }

        if ($canViewBilling && $billing?->is_overdue === true) {
            $alerts->push([
                'level' => 'danger',
                'title' => 'Deuda vencida',
                'description' => 'Existe saldo pendiente con fecha de vencimiento superada.',
                'href' => route('billing.show', $case),
            ]);
        }

        return $alerts;
    }

    /**
     * @return list<array{label: string, state: string}>
     */
    /**
     * @return Collection<int, array{product: mixed, lot: ?InventoryLot, reserved_qty: int, used_qty: int, returned_qty: int, failure_qty: int, difference_qty: ?int, unit_price: mixed, subtotal: mixed, has_consumption: bool}>
     */
    private function controlMaterialRows(SurgeryCase $case): Collection
    {
        $materialsByReservation = $case->materialsUsed->keyBy('reservation_id');
        $reservationRows = $case->reservations->map(function (Reservation $reservation) use ($materialsByReservation): array {
            /** @var ?CaseMaterialUsed $material */
            $material = $materialsByReservation->get($reservation->id);

            return $this->controlMaterialRow($reservation->inventoryLot, $reservation, $material);
        });
        $materialRowsWithoutReservation = $case->materialsUsed
            ->filter(function (CaseMaterialUsed $material) use ($case): bool {
                return $material->reservation_id === null
                    || ! $case->reservations->contains('id', $material->reservation_id);
            })
            ->map(fn (CaseMaterialUsed $material): array => $this->controlMaterialRow(
                $material->inventoryLot,
                null,
                $material,
            ));

        return $reservationRows->concat($materialRowsWithoutReservation)->values();
    }

    /**
     * @return array{product: mixed, lot: ?InventoryLot, reserved_qty: int, used_qty: int, returned_qty: int, failure_qty: int, difference_qty: ?int, unit_price: mixed, subtotal: mixed, has_consumption: bool}
     */
    private function controlMaterialRow(
        ?InventoryLot $lot,
        ?Reservation $reservation,
        ?CaseMaterialUsed $material,
    ): array {
        return [
            'product' => $lot?->product,
            'lot' => $lot,
            'reserved_qty' => (int) ($material?->reserved_qty ?? $reservation?->quantity ?? 0),
            'used_qty' => (int) ($material?->used_qty ?? 0),
            'returned_qty' => (int) ($material?->returned_qty ?? 0),
            'failure_qty' => (int) ($material?->failure_qty ?? 0),
            'difference_qty' => $material ? (int) $material->difference_qty : null,
            'unit_price' => $material?->unit_price,
            'subtotal' => $material?->subtotal,
            'has_consumption' => $material !== null,
        ];
    }

    /**
     * @return Collection<int, AuditLog>
     */
    private function caseAuditLogs(SurgeryCase $case): Collection
    {
        $targets = [
            SurgeryCase::class => [$case->id],
            Reservation::class => $case->reservations->modelKeys(),
            CasePreparation::class => $case->preparation ? [$case->preparation->id] : [],
            CaseResourceAssignment::class => $case->resourceAssignments->modelKeys(),
            CaseMaterialSent::class => $case->materialsSent->modelKeys(),
            CaseMaterialUsed::class => $case->materialsUsed->modelKeys(),
            CaseReturn::class => $case->returns->modelKeys(),
            Failure::class => $case->failures->modelKeys(),
            CaseValuation::class => $case->valuation ? [$case->valuation->id] : [],
            Approval::class => $case->valuation?->approval ? [$case->valuation->approval->id] : [],
            BillingRecord::class => $case->billingRecord ? [$case->billingRecord->id] : [],
            CaseReconciliation::class => $case->reconciliation ? [$case->reconciliation->id] : [],
            DocumentEvidence::class => $case->documents->modelKeys(),
        ];

        return AuditLog::query()
            ->with('user')
            ->where(function (Builder $query) use ($targets): void {
                foreach ($targets as $type => $ids) {
                    if ($ids === []) {
                        continue;
                    }

                    $query->orWhere(function (Builder $targetQuery) use ($type, $ids): void {
                        $targetQuery
                            ->where('auditable_type', $type)
                            ->whereIn('auditable_id', $ids);
                    });
                }
            })
            ->latest('created_at')
            ->limit(10)
            ->get();
    }

    private function auditSummary(AuditLog $audit): string
    {
        $summary = collect($audit->after ?? [])
            ->except([
                'id',
                'case_id',
                'reservation_id',
                'inventory_lot_id',
                'product_id',
                'valuation_id',
            ])
            ->filter(fn (mixed $value): bool => is_scalar($value) && filled($value))
            ->take(3)
            ->map(function (mixed $value, string|int $key): string {
                $displayValue = is_bool($value) ? ($value ? 'Si' : 'No') : Str::limit((string) $value, 100);

                return Str::headline((string) $key).': '.$displayValue;
            })
            ->implode(', ');

        return $summary !== '' ? $summary : 'Movimiento registrado en el caso.';
    }
}
