<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryAdjustmentRequest;
use App\Http\Requests\UpdateInventoryLotRequest;
use App\Models\AuditLog;
use App\Models\InventoryAdjustment;
use App\Models\InventoryImportIssue;
use App\Models\InventoryLot;
use App\Models\OperationalAlert;
use App\Services\Inventory\InventoryAdjustmentService;
use App\Services\Inventory\InventoryLotService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', InventoryLot::class);

        $status = $request->string('status')->toString();
        $lots = InventoryLot::query()
            ->with(['product', 'warehouse'])
            ->withSum(['reservations as reserved_active' => fn (Builder $query) => $query->where('status', 'active')], 'quantity')
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->latest('updated_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $lots->getCollection()->each(function (InventoryLot $lot): void {
            $lot->setAttribute('available_net', max(0, (int) $lot->quantity - (int) ($lot->reserved_active ?? 0)));
        });
        $productIds = $lots->getCollection()->pluck('product_id')->unique()->values();
        $lotValues = $lots->getCollection()->pluck('lot')->filter()->unique()->values();
        $negativeIssues = InventoryImportIssue::query()
            ->where('status', 'pending')
            ->whereIn('product_id', $productIds)
            ->whereIn('lot', $lotValues)
            ->whereHas('catalogImport', fn (Builder $query) => $query->where('status', 'committed'))
            ->get()
            ->groupBy(fn (InventoryImportIssue $issue): string => $issue->product_id.'|'.$issue->lot);

        $lots->getCollection()->each(function (InventoryLot $lot) use ($negativeIssues): void {
            $lot->setAttribute(
                'negative_issues',
                $negativeIssues->get($lot->product_id.'|'.$lot->lot, collect()),
            );
        });

        return view('inventory.index', [
            'lots' => $lots,
            'selectedStatus' => $status,
        ]);
    }

    public function show(InventoryLot $lot): View
    {
        $this->authorize('view', $lot);

        $lot->load([
            'product',
            'warehouse',
            'reservations' => fn ($query) => $query
                ->with(['case.institution', 'case.doctor', 'reservedBy'])
                ->latest('id'),
            'adjustments.responsibleUser',
            'failures' => fn ($query) => $query
                ->with(['case.institution', 'reportedBy', 'responsibleTechnical'])
                ->latest('id'),
            'returns' => fn ($query) => $query
                ->with(['case.institution', 'case.doctor', 'inspectedBy', 'inspectionResponsible', 'technicalFailure'])
                ->latest('id'),
            'documents' => fn ($query) => $query->visibleTo(auth()->user())->with(['uploadedBy', 'validatedBy']),
        ]);

        $productLots = InventoryLot::query()
            ->with(['warehouse'])
            ->withSum(['reservations as reserved_active' => fn (Builder $query) => $query->where('status', 'active')], 'quantity')
            ->where('product_id', $lot->product_id)
            ->orderBy('warehouse_id')
            ->orderBy('expiry')
            ->get();

        $productLots->each(function (InventoryLot $productLot): void {
            $productLot->setAttribute('available_net', max(0, (int) $productLot->quantity - (int) ($productLot->reserved_active ?? 0)));
        });

        $negativeIssues = InventoryImportIssue::query()
            ->where('status', 'pending')
            ->where('product_id', $lot->product_id)
            ->when(
                $lot->lot === null,
                fn (Builder $query) => $query->whereNull('lot'),
                fn (Builder $query) => $query->where('lot', $lot->lot),
            )
            ->whereHas('catalogImport', fn (Builder $query) => $query->where('status', 'committed'))
            ->with(['warehouse', 'catalogImport'])
            ->latest('id')
            ->get();

        $canAudit = auth()->user()?->can('inventory.audit') || auth()->user()?->can('audit.view');
        $auditLogs = collect();

        if ($canAudit) {
            $adjustmentIds = $lot->adjustments->modelKeys();
            $auditLogs = AuditLog::query()
                ->with('user')
                ->where(function (Builder $query) use ($lot, $adjustmentIds): void {
                    $query->where(function (Builder $audits) use ($lot): void {
                        $audits->where('auditable_type', InventoryLot::class)
                            ->where('auditable_id', $lot->id);
                    })->orWhere(function (Builder $audits) use ($adjustmentIds): void {
                        $audits->where('auditable_type', InventoryAdjustment::class)
                            ->whereIn('auditable_id', $adjustmentIds ?: [0]);
                    });
                })
                ->latest('created_at')
                ->get();
        }

        $slaAlerts = OperationalAlert::query()
            ->where('alertable_type', InventoryLot::class)
            ->where('alertable_id', $lot->id)
            ->whereIn('status', array_merge(OperationalAlert::OPEN_STATUSES, ['expired']))
            ->latest('detected_at')
            ->get();

        return view('inventory.show', [
            'lot' => $lot,
            'productLots' => $productLots,
            'auditLogs' => $auditLogs,
            'canAudit' => $canAudit,
            'negativeIssues' => $negativeIssues,
            'slaAlerts' => $slaAlerts,
        ]);
    }

    public function edit(InventoryLot $lot): View
    {
        $this->authorize('update', $lot);
        $lot->load(['product', 'warehouse']);

        return view('inventory.edit', [
            'lot' => $lot,
            'hasActiveReservations' => $lot->reservations()->where('status', 'active')->exists(),
        ]);
    }

    public function update(
        UpdateInventoryLotRequest $request,
        InventoryLot $lot,
        InventoryLotService $inventoryLotService,
    ): RedirectResponse {
        try {
            $inventoryLotService->update($lot, $request->validated());
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['inventory' => $exception->getMessage()]);
        }

        return redirect()
            ->route('inventory.show', $lot)
            ->with('status', 'Lote actualizado y auditado correctamente.');
    }

    public function adjustForm(InventoryLot $lot): View
    {
        $this->authorize('adjust', $lot);
        $lot->load(['product', 'warehouse']);

        return view('inventory.adjust', ['lot' => $lot]);
    }

    public function adjust(
        StoreInventoryAdjustmentRequest $request,
        InventoryLot $lot,
        InventoryAdjustmentService $inventoryAdjustmentService,
    ): RedirectResponse {
        try {
            $inventoryAdjustmentService->create($lot, $request->validated(), (int) $request->user()->id);
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['quantity_adjustment' => $exception->getMessage()]);
        }

        return redirect()
            ->route('inventory.show', $lot)
            ->with('status', 'Ajuste de inventario registrado y auditado correctamente.');
    }
}
