<?php

namespace App\Http\Controllers;

use App\Enums\FailureMoment;
use App\Enums\FailureSeverity;
use App\Enums\FailureStatus;
use App\Enums\FailureType;
use App\Http\Requests\CloseFailureRequest;
use App\Http\Requests\ReleaseFailureRequest;
use App\Http\Requests\RetireFailureRequest;
use App\Http\Requests\StoreFailureRequest;
use App\Http\Requests\UpdateFailureRequest;
use App\Models\AuditLog;
use App\Models\Failure;
use App\Models\InventoryLot;
use App\Models\OperationalAlert;
use App\Models\Product;
use App\Models\SurgeryCase;
use App\Models\User;
use App\Services\Failures\FailureManagementService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FailureController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Failure::class);
        $status = $request->string('status')->toString();
        $validStatus = in_array($status, FailureStatus::values(), true) ? $status : null;

        $failures = Failure::query()
            ->with(['product', 'inventoryLot.warehouse', 'case.institution', 'reportedBy', 'responsibleTechnical'])
            ->when($validStatus !== null, fn (Builder $query) => $query->where('status', $validStatus))
            ->when($request->filled('severity'), fn (Builder $query) => $query->where('severity', $request->string('severity')->toString()))
            ->when($request->filled('failure_type'), fn (Builder $query) => $query->where('failure_type', $request->string('failure_type')->toString()))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('failures.index', [
            'failures' => $failures,
            'filters' => array_merge($request->only(['severity', 'failure_type']), ['status' => $validStatus]),
            'labels' => $this->labels(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Failure::class);

        return view('failures.create', $this->formOptions($request));
    }

    public function store(StoreFailureRequest $request, FailureManagementService $service): RedirectResponse
    {
        try {
            $failure = $service->report($request->validated());
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['failure' => $exception->getMessage()]);
        }

        return redirect()->route('failures.show', $failure)
            ->with('status', 'Falla registrada. El bloqueo preventivo fue auditado cuando correspondia.');
    }

    public function show(Failure $failure): View
    {
        $this->authorize('view', $failure);
        $failure->load([
            'product',
            'inventoryLot.product',
            'inventoryLot.warehouse',
            'case.institution',
            'case.doctor',
            'reportedBy',
            'responsibleTechnical',
            'reviewedBy',
            'releasedBy',
            'retiredBy',
            'documents.uploadedBy',
            'documents.validatedBy',
        ]);

        $canAudit = auth()->user()?->can('audit.view') ?? false;
        $auditLogs = collect();

        if ($canAudit) {
            $auditLogs = AuditLog::query()
                ->with('user')
                ->where(function (Builder $query) use ($failure): void {
                    $query->where(function (Builder $failureQuery) use ($failure): void {
                        $failureQuery->where('auditable_type', Failure::class)
                            ->where('auditable_id', $failure->id);
                    });

                    if ($failure->inventory_lot_id !== null) {
                        $query->orWhere(function (Builder $lotQuery) use ($failure): void {
                            $lotQuery->where('auditable_type', InventoryLot::class)
                                ->where('auditable_id', $failure->inventory_lot_id);
                        });
                    }
                })
                ->latest('created_at')
                ->get();
        }

        $slaAlerts = OperationalAlert::query()
            ->where('alertable_type', Failure::class)
            ->where('alertable_id', $failure->id)
            ->whereIn('status', array_merge(OperationalAlert::OPEN_STATUSES, ['expired']))
            ->latest('detected_at')
            ->get();

        return view('failures.show', [
            'failure' => $failure,
            'auditLogs' => $auditLogs,
            'canAudit' => $canAudit,
            'labels' => $this->labels(),
            'slaAlerts' => $slaAlerts,
        ]);
    }

    public function edit(Failure $failure, Request $request): View|RedirectResponse
    {
        $this->authorize('update', $failure);

        if (in_array($failure->status, [FailureStatus::Liberada->value, FailureStatus::DadaDeBaja->value, FailureStatus::Cerrada->value], true)) {
            return redirect()->route('failures.show', $failure)
                ->with('error', 'La falla se encuentra en un estado final y no permite edicion.');
        }

        $failure->load(['product', 'inventoryLot', 'case']);

        return view('failures.edit', $this->formOptions($request, $failure));
    }

    public function update(UpdateFailureRequest $request, Failure $failure, FailureManagementService $service): RedirectResponse
    {
        try {
            $service->update($failure, $request->validated());
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['failure' => $exception->getMessage()]);
        }

        return redirect()->route('failures.show', $failure)
            ->with('status', 'Seguimiento de falla actualizado y auditado.');
    }

    public function release(ReleaseFailureRequest $request, Failure $failure, FailureManagementService $service): RedirectResponse
    {
        $this->authorize('release', $failure);

        try {
            $service->release($failure, $request->validated());
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['failure' => $exception->getMessage()]);
        }

        return redirect()->route('failures.show', $failure)
            ->with('status', 'Falla liberada y accion de inventario auditada.');
    }

    public function retire(RetireFailureRequest $request, Failure $failure, FailureManagementService $service): RedirectResponse
    {
        $this->authorize('retire', $failure);

        try {
            $service->retire($failure, $request->validated());
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['failure' => $exception->getMessage()]);
        }

        return redirect()->route('failures.show', $failure)
            ->with('status', 'Falla y lote enviados a baja con trazabilidad.');
    }

    public function close(CloseFailureRequest $request, Failure $failure, FailureManagementService $service): RedirectResponse
    {
        $this->authorize('close', $failure);

        try {
            $service->close($failure, $request->validated());
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['failure' => $exception->getMessage()]);
        }

        return redirect()->route('failures.show', $failure)
            ->with('status', 'Falla cerrada y auditada.');
    }

    /** @return array<string, mixed> */
    private function formOptions(Request $request, ?Failure $failure = null): array
    {
        $selectedLotId = $request->integer('inventory_lot_id') ?: $failure?->inventory_lot_id;
        $selectedProductId = $request->integer('product_id') ?: $failure?->product_id;

        if ($selectedProductId === null && $selectedLotId !== null) {
            $selectedProductId = InventoryLot::query()->whereKey($selectedLotId)->value('product_id');
        }

        return [
            'failure' => $failure,
            'products' => Product::query()->where('active', true)->orderBy('product_code')->get(),
            'lots' => InventoryLot::query()->with(['product', 'warehouse'])->latest('id')->get(),
            'cases' => SurgeryCase::query()->with(['institution', 'doctor'])->latest('id')->limit(100)->get(),
            'technicalUsers' => $this->technicalUsers(),
            'labels' => $this->labels(),
            'selectedProductId' => $selectedProductId,
            'selectedLotId' => $selectedLotId,
            'selectedCaseId' => $request->integer('case_id') ?: $failure?->case_id,
        ];
    }

    private function technicalUsers(): Collection
    {
        return User::query()
            ->where('active', true)
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', ['Administrador', 'Direccion Tecnica']))
            ->orderBy('name')
            ->get();
    }

    /** @return array<string, array<string, string>> */
    private function labels(): array
    {
        return [
            'types' => collect(FailureType::cases())->mapWithKeys(fn (FailureType $type): array => [$type->value => $type->label()])->all(),
            'moments' => collect(FailureMoment::cases())->mapWithKeys(fn (FailureMoment $moment): array => [$moment->value => $moment->label()])->all(),
            'severities' => collect(FailureSeverity::cases())->mapWithKeys(fn (FailureSeverity $severity): array => [$severity->value => $severity->label()])->all(),
            'statuses' => FailureStatus::labels(),
        ];
    }
}
