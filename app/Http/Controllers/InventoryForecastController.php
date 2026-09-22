<?php

namespace App\Http\Controllers;

use App\Services\Inventory\ReplenishmentForecastService;
use App\Support\CsvExportSanitizer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryForecastController extends Controller
{
    private const FORECAST_ROLES = [
        'Administrador',
        'Jefe de Linea',
        'Direccion Tecnica',
        'Almacen',
        'Gerencia',
    ];

    public function index(Request $request, ReplenishmentForecastService $forecastService): View
    {
        $this->authorizeForecast();

        $filters = $this->validatedFilters($request);

        return view('inventory.forecast', [
            'forecast' => $forecastService->summary($filters),
            'filters' => $filters,
        ]);
    }

    public function export(
        Request $request,
        ReplenishmentForecastService $forecastService,
        CsvExportSanitizer $csvExportSanitizer,
    ): StreamedResponse {
        $this->authorizeForecast();

        $filters = $this->validatedFilters($request);
        $rows = $forecastService->summary($filters)['rows'];

        return response()->streamDownload(function () use ($rows, $csvExportSanitizer): void {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'Tipo de cirugía',
                'Código de producto',
                'Producto',
                'Familia',
                'Subfamilia',
                'Longitud cm',
                'Diámetro mm',
                'Tipo',
                'Stock inmediato neto',
                'Stock YSAN',
                'Reservado',
                'Mínimo',
                'Objetivo',
                'Déficit mínimo',
                'Déficit objetivo',
                'Compra sugerida',
                'Urgencia',
                'Acción',
                'Observación',
            ], ';', '"', '\\');

            foreach ($rows as $row) {
                fputcsv($handle, $csvExportSanitizer->sanitizeRow([
                    $row['surgery_type'],
                    $row['product_code'],
                    $row['product_name'],
                    $row['family'],
                    $row['subfamily'],
                    $row['length_cm'],
                    $row['diameter_mm'],
                    $row['product_type'],
                    $row['stock_immediate_net'],
                    $row['stock_ysan'],
                    $row['reserved_active'],
                    $row['minimum'],
                    $row['target'],
                    $row['deficit_minimum'],
                    $row['deficit_target'],
                    $row['suggested_purchase'],
                    $row['urgency_label'],
                    $row['action'],
                    $row['observation'],
                ]), ';', '"', '\\');
            }

            fclose($handle);
        }, 'plan-reposicion-mr8.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function authorizeForecast(): void
    {
        $user = auth()->user();

        abort_unless($user?->can('inventory.view'), 403);
        abort_unless($user?->hasAnyRole(self::FORECAST_ROLES), 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'surgery_type' => ['nullable', 'string', 'max:100'],
            'urgency' => ['nullable', 'in:critical,high,normal'],
            'length' => ['nullable', 'numeric'],
            'diameter' => ['nullable', 'numeric'],
            'product_type' => ['nullable', 'string', 'max:30'],
        ]);
    }
}
