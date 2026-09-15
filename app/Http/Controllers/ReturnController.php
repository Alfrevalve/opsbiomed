<?php

namespace App\Http\Controllers;

use App\Http\Requests\InspectCaseReturnRequest;
use App\Models\AuditLog;
use App\Models\CaseMaterialUsed;
use App\Models\CaseReturn;
use App\Models\Failure;
use App\Models\Institution;
use App\Models\InventoryLot;
use App\Models\OperationalAlert;
use App\Models\Product;
use App\Models\User;
use App\Services\Returns\ReturnInspectionService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReturnController extends Controller
{
    /** @var array<string, string> */
    private const CONDITIONS = [
        'pendiente_inspeccion' => 'Pendiente de inspeccion',
        'inspeccionado' => 'Inspeccionado',
        'liberado' => 'Liberado',
        'cuarentena' => 'Cuarentena',
        'bloqueado' => 'Bloqueado',
        'desvalorizado' => 'Desvalorizado',
        'dado_de_baja' => 'Dado de baja',
    ];

    /** @var array<string, string> */
    private const INSPECTION_RESULTS = [
        'apto_para_retorno' => 'Apto para retorno',
        'requiere_limpieza' => 'Requiere limpieza',
        'requiere_revision_tecnica' => 'Requiere revision tecnica',
        'falla_detectada' => 'Falla detectada',
        'no_reutilizable' => 'No reutilizable',
        'baja' => 'Dar de baja',
    ];

    public function index(Request $request): View
    {
        $this->authorize('viewAny', CaseReturn::class);

        $status = $request->string('status')->toString();
        $institutionId = $request->integer('institution_id') ?: null;
        $productId = $request->integer('product_id') ?: null;
        $responsibleId = $request->integer('responsible_id') ?: null;
        $date = $request->string('date')->toString();

        $returns = CaseReturn::query()
            ->with([
                'case.institution',
                'case.doctor',
                'inventoryLot.product',
                'inventoryLot.warehouse',
                'inspectedBy',
                'inspectionResponsible',
            ])
            ->when(array_key_exists($status, self::CONDITIONS), fn (Builder $query) => $query->where('condition', $status))
            ->when($institutionId, fn (Builder $query) => $query->whereHas('case', fn (Builder $case) => $case->where('institution_id', $institutionId)))
            ->when($productId, fn (Builder $query) => $query->whereHas('inventoryLot', fn (Builder $lot) => $lot->where('product_id', $productId)))
            ->when($responsibleId, fn (Builder $query) => $query->where('inspection_responsible_id', $responsibleId))
            ->when($date !== '', fn (Builder $query) => $query->whereDate('created_at', $date))
            ->latest('created_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('returns.index', [
            'returns' => $returns,
            'conditions' => self::CONDITIONS,
            'institutions' => Institution::query()->orderBy('name')->get(),
            'products' => Product::query()->where('active', true)->orderBy('product_code')->get(),
            'responsibleUsers' => User::query()->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function show(CaseReturn $return): View
    {
        $this->authorize('view', $return);

        $return->load([
            'case.institution',
            'case.doctor',
            'case.patient',
            'case.materialsUsed.inventoryLot.product',
            'case.failures.inventoryLot.product',
            'inventoryLot.product',
            'inventoryLot.warehouse',
            'inspectedBy',
            'inspectionResponsible',
            'technicalFailure',
            'documents.uploadedBy',
            'documents.validatedBy',
        ]);

        $materialUsed = $return->case?->materialsUsed?->first(
            fn (CaseMaterialUsed $material): bool => $material->inventory_lot_id === $return->inventory_lot_id,
        );
        $canAudit = auth()->user()?->can('returns.audit') || auth()->user()?->can('audit.view');
        $auditLogs = collect();

        if ($canAudit) {
            $targets = [
                [CaseReturn::class, $return->id],
                [InventoryLot::class, $return->inventory_lot_id],
            ];

            if ($return->technical_failure_id !== null) {
                $targets[] = [Failure::class, $return->technical_failure_id];
            }

            $auditLogs = AuditLog::query()
                ->with('user')
                ->where(function (Builder $query) use ($targets): void {
                    foreach ($targets as [$type, $id]) {
                        $query->orWhere(function (Builder $targetQuery) use ($type, $id): void {
                            $targetQuery->where('auditable_type', $type)->where('auditable_id', $id);
                        });
                    }
                })
                ->latest('created_at')
                ->get();
        }

        $slaAlerts = OperationalAlert::query()
            ->where('alertable_type', CaseReturn::class)
            ->where('alertable_id', $return->id)
            ->whereIn('status', array_merge(OperationalAlert::OPEN_STATUSES, ['expired']))
            ->latest('detected_at')
            ->get();

        return view('returns.show', [
            'return' => $return,
            'materialUsed' => $materialUsed,
            'conditions' => self::CONDITIONS,
            'inspectionResults' => self::INSPECTION_RESULTS,
            'auditLogs' => $auditLogs,
            'slaAlerts' => $slaAlerts,
            'canAudit' => $canAudit,
        ]);
    }

    public function inspectForm(CaseReturn $return): View|RedirectResponse
    {
        $this->authorize('inspect', $return);

        if ($return->condition !== 'pendiente_inspeccion') {
            return redirect()->route('returns.show', $return)
                ->with('error', 'Esta devolucion ya fue inspeccionada.');
        }

        $return->load([
            'case.institution',
            'case.doctor',
            'inventoryLot.product',
            'inventoryLot.warehouse',
        ]);

        return view('returns.inspect', [
            'return' => $return,
            'inspectionResults' => self::INSPECTION_RESULTS,
            'responsibleUsers' => User::query()->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function inspect(
        InspectCaseReturnRequest $request,
        CaseReturn $return,
        ReturnInspectionService $inspectionService,
    ): RedirectResponse {
        $this->authorize('inspect', $return);

        try {
            $inspectionService->inspect($return, $request->validated());
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['inspection' => $exception->getMessage()]);
        }

        return redirect()
            ->route('returns.show', $return)
            ->with('status', 'Devolucion inspeccionada y estado de inventario actualizado.');
    }
}
