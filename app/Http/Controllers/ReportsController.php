<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportFilterRequest;
use App\Services\Reports\ExecutiveReportService;
use App\Support\CsvExportSanitizer;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    private const SECTION_PERMISSIONS = [
        'operations' => 'reports.operations',
        'commercial' => 'reports.commercial',
        'billing' => 'reports.billing',
        'inventory' => 'reports.inventory',
    ];

    public function index(): View
    {
        $this->authorizeRoot();

        return view('reports.index', [
            'sections' => collect(self::SECTION_PERMISSIONS)
                ->filter(fn (string $permission): bool => auth()->user()->can($permission))
                ->keys()
                ->all(),
        ]);
    }

    public function operations(ReportFilterRequest $request, ExecutiveReportService $service): View
    {
        return $this->renderReport('operations', $request, $service);
    }

    public function commercial(ReportFilterRequest $request, ExecutiveReportService $service): View
    {
        return $this->renderReport('commercial', $request, $service);
    }

    public function billing(ReportFilterRequest $request, ExecutiveReportService $service): View
    {
        return $this->renderReport('billing', $request, $service);
    }

    public function inventory(ReportFilterRequest $request, ExecutiveReportService $service): View
    {
        return $this->renderReport('inventory', $request, $service);
    }

    public function export(
        ReportFilterRequest $request,
        string $type,
        ExecutiveReportService $service,
        CsvExportSanitizer $csvExportSanitizer,
    ): StreamedResponse {
        abort_unless(array_key_exists($type, self::SECTION_PERMISSIONS), Response::HTTP_NOT_FOUND);
        $this->authorizeSection($type, true);
        $filters = $service->normalizeFilters($request->validated());
        $report = $service->{$type}(auth()->user(), $filters);
        $rows = $this->exportRows($type, $report);

        return response()->streamDownload(function () use ($rows, $type, $csvExportSanitizer): void {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $this->exportHeaders($type), ';');

            foreach ($rows as $row) {
                fputcsv($handle, $csvExportSanitizer->sanitizeRow($row), ';');
            }

            fclose($handle);
        }, 'reporte-'.$type.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function renderReport(string $type, ReportFilterRequest $request, ExecutiveReportService $service): View
    {
        $this->authorizeSection($type);
        $filters = $service->normalizeFilters($request->validated());
        $report = $service->{$type}(auth()->user(), $filters);

        return view('reports.'.$type, [
            'report' => $report,
            'filters' => $filters,
            'options' => $service->filterOptions(),
            'printMode' => $request->boolean('print'),
            'section' => $type,
        ]);
    }

    private function authorizeRoot(): void
    {
        abort_unless(auth()->user()?->can('reports.view'), Response::HTTP_FORBIDDEN);
    }

    private function authorizeSection(string $type, bool $forExport = false): void
    {
        $permission = self::SECTION_PERMISSIONS[$type] ?? null;
        abort_unless($permission && auth()->user()?->can($permission), Response::HTTP_FORBIDDEN);

        if ($forExport) {
            abort_unless(auth()->user()?->can('reports.export'), Response::HTTP_FORBIDDEN);
        }
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<list<string|int|float|null>>
     */
    private function exportRows(string $type, array $report): array
    {
        return match ($type) {
            'operations' => $this->collectionRows($report['by_date'], fn (array $row): array => [$row['label'], $row['cases']]),
            'commercial' => $this->collectionRows($report['by_product'], fn (array $row): array => [$row['label'], $row['units'], $row['cases'], $row['value']]),
            'billing' => $this->collectionRows($report['records'], fn (array $row): array => [
                $row['case_code'],
                $row['institution'],
                $row['scheduled_at'],
                $row['invoice_status'],
                $row['amount'],
                $row['amount_paid'],
                $row['balance'],
                $row['payment_status'],
                $row['due_date'],
            ]),
            'inventory' => $this->collectionRows($report['forecast'], fn (array $row): array => [
                $row['surgery_type'],
                $row['product_code'],
                $row['length_cm'],
                $row['diameter_mm'],
                $row['product_type'],
                $row['stock_immediate_net'],
                $row['stock_ysan'],
                $row['reserved_active'],
                $row['minimum'],
                $row['target'],
                $row['urgency_label'],
                $row['suggested_purchase'],
                $row['action'],
            ]),
            default => [],
        };
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<list<string|int|float|null>>
     */
    private function collectionRows(Collection $rows, callable $mapper): array
    {
        return $rows->map($mapper)->values()->all();
    }

    /**
     * @return list<string>
     */
    private function exportHeaders(string $type): array
    {
        return match ($type) {
            'operations' => ['Fecha de cirugia', 'Casos'],
            'commercial' => ['Producto', 'Unidades usadas', 'Casos', 'Valorizado preliminar'],
            'billing' => ['Caso', 'Institucion', 'Fecha cirugia', 'Estado facturacion', 'Monto', 'Pagado', 'Saldo', 'Estado pago', 'Vencimiento'],
            'inventory' => ['Tipo de cirugia', 'Producto/codigo', 'Longitud cm', 'Diametro mm', 'Tipo', 'Stock inmediato neto', 'Stock YSAN', 'Reservado', 'Minimo', 'Objetivo', 'Urgencia', 'Compra sugerida', 'Accion'],
            default => [],
        };
    }
}
