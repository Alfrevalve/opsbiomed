<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScanTraceCodeRequest;
use App\Models\InventoryLot;
use App\Models\SurgeryCase;
use App\Models\User;
use App\Services\Traceability\TraceCodeService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TraceController extends Controller
{
    public function index(): View
    {
        return view('trace.index', ['scanResult' => null]);
    }

    public function scan(ScanTraceCodeRequest $request, TraceCodeService $traceCodeService): View
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $traceCode = (string) $request->validated('trace_code');
        $scanResult = $traceCodeService->scan($traceCode, $user);

        return view('trace.index', compact('scanResult', 'traceCode'));
    }

    public function lotLabels(Request $request, TraceCodeService $traceCodeService): View
    {
        $this->authorize('viewAny', InventoryLot::class);

        $lotIds = collect(explode(',', $request->string('lots')->toString()))
            ->filter(fn (string $id): bool => ctype_digit($id))
            ->map(fn (string $id): int => (int) $id)
            ->values();
        $lots = InventoryLot::query()
            ->with(['product', 'warehouse'])
            ->when($lotIds->isNotEmpty(), fn ($query) => $query->whereIn('id', $lotIds))
            ->orderBy('product_id')
            ->orderBy('lot')
            ->paginate(24)
            ->withQueryString();

        $lots->getCollection()->each(function (InventoryLot $lot) use ($traceCodeService): void {
            $traceCodeService->recordLabelPrinted($lot);
        });

        return view('trace.labels.lots', compact('lots'));
    }

    public function caseLabels(SurgeryCase $case, TraceCodeService $traceCodeService): View
    {
        $this->authorize('view', $case);

        $case->load([
            'institution',
            'doctor',
            'patient',
            'surgeryType',
            'reservations.inventoryLot.product',
            'reservations.inventoryLot.warehouse',
        ]);

        $traceCodeService->recordLabelPrinted($case, $case->id);
        $case->reservations
            ->pluck('inventoryLot')
            ->filter()
            ->unique('id')
            ->each(fn (InventoryLot $lot): mixed => $traceCodeService->recordLabelPrinted($lot, $case->id));

        return view('trace.labels.case', compact('case'));
    }
}
