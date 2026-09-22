<?php

namespace App\Services\Catalog;

use App\Enums\InventoryStatus;
use App\Enums\WarehouseType;
use App\Imports\CatalogRowsImport;
use App\Models\CatalogImport;
use App\Models\CatalogImportRow;
use App\Models\InventoryImportIssue;
use App\Models\InventoryLot;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;
use Throwable;

class CatalogImportService
{
    private const REQUIRED_HEADERS = [
        'product_line',
        'family',
        'subfamily',
        'product_code',
        'product_name',
        'lot',
        'detail_expiry',
        'location',
    ];

    private const WAREHOUSE_COLUMNS = [
        'principal' => 'ALMACEN PRINCIPAL',
        'consignacion' => 'ALMACEN CONSIGNACION',
        'desvalorizado' => 'ALMACEN DESVALORIZAD',
        'ysan' => 'ALMACEN YSAN',
    ];

    private const HEADER_ALIASES = [
        'product_line' => ['producto linea', 'linea', 'producto line'],
        'family' => ['producto familia', 'familia'],
        'subfamily' => ['producto subfamilia', 'subfamilia'],
        'product_code' => ['producto codigo', 'producto code', 'codigo', 'sku'],
        'product_name' => ['producto nombre', 'nombre', 'descripcion'],
        'regulatory_record' => ['registro sanitario', 'registro regsan', 'regsan'],
        'regulatory_expiry' => ['vencimiento regsan', 'vencimiento registro sanitario', 'vencimiento reg'],
        'lot' => ['detalle lote', 'lote'],
        'serial' => ['detalle serie', 'serie'],
        'detail_expiry' => ['detalle vencimiento', 'vencimiento lote', 'vencimiento'],
        'location' => ['detalle ubicacion', 'ubicacion', 'almacen'],
        'principal' => ['almacen principal', 'almacen principal cantidad'],
        'consignacion' => ['almacen consignacion', 'almacen consignacion cantidad'],
        'desvalorizado' => ['almacen desvalorizad', 'almacen desvalorizado', 'almacen desvalorizada'],
        'ysan' => ['almacen ysan'],
        'total' => ['total', 'stock total', 'cantidad total'],
    ];

    private const REUSABLE_MARKERS = [
        'ec300',
        'ipc',
        'consola',
        'em800',
        'motor',
        'ef200',
        'pedal',
        'ea600',
        'cable',
        'a-mr8',
        'mr8-aa',
        'mr8-as',
        'mr8-af',
        'mr8-ad',
        'mr8-at',
        'ca800',
        'bandeja',
        'pa300',
        'pa305',
        'pa310',
        'pa320',
        'cepillo',
        'tt12',
        'tt14',
        'tubo telescopico',
    ];

    private const CONSUMABLE_MARKERS = [
        'fresa',
        'cuchilla',
        'irrigacion',
        'ird300',
        'f1/',
        'f2/',
        'f3/',
        'ta',
        'ba',
        'mh',
        'ac',
    ];

    private const NA_EXPIRY_VALUES = ['n/a', 'na', 'no aplica', 'sin vencimiento'];

    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function stage(UploadedFile $file, int $userId): CatalogImport
    {
        $disk = 'private';
        $path = $file->store('catalog-imports', $disk);
        $import = CatalogImport::create([
            'original_filename' => $file->getClientOriginalName(),
            'disk' => $disk,
            'stored_path' => $path,
            'checksum' => $file->getRealPath() ? hash_file('sha256', $file->getRealPath()) : null,
            'status' => 'processing',
            'uploaded_by' => $userId,
        ]);

        try {
            $spreadsheetRows = Excel::toArray(new CatalogRowsImport, $path, $disk);
            $rows = $spreadsheetRows[0] ?? [];
            $summary = $this->stageRows($import, $rows);
            $import->update($summary);
            $import->refresh();
            $this->auditLogger->record('catalog.import.staged', $import, [], [
                'filename' => $import->original_filename,
                'rows' => $import->total_rows,
                'critical_errors' => $import->critical_errors,
                'warnings' => $import->warnings,
                'inconsistencies' => $import->inconsistencies,
            ]);

            return $import;
        } catch (Throwable $exception) {
            $import->update([
                'status' => 'failed',
                'critical_errors' => 1,
                'error_summary' => [['row' => 0, 'errors' => [$exception->getMessage()], 'warnings' => []]],
            ]);

            throw new RuntimeException('No fue posible leer el archivo Excel: '.$exception->getMessage(), previous: $exception);
        }
    }

    public function commit(CatalogImport $import, bool $confirmNegativeInconsistencies = false): CatalogImport
    {
        $import = DB::transaction(function () use ($import, $confirmNegativeInconsistencies): CatalogImport {
            $import = CatalogImport::query()
                ->with('rows')
                ->lockForUpdate()
                ->findOrFail($import->id);

            if ($import->status === 'committed') {
                throw new DomainException('La importacion ya fue confirmada.');
            }

            if ($import->critical_errors > 0) {
                throw new DomainException('La importacion tiene errores criticos y no puede confirmarse.');
            }

            if ($import->inconsistencies > 0 && ! $confirmNegativeInconsistencies) {
                throw new DomainException('Debes confirmar que las cantidades negativas se importaran como inconsistencias y no como stock disponible.');
            }

            $warehouses = $this->ensureWarehouses();
            Warehouse::query()
                ->whereKey($warehouses['principal']->id)
                ->lockForUpdate()
                ->firstOrFail();
            $products = [];
            $aggregatedLots = [];

            InventoryLot::query()
                ->whereNotNull('catalog_import_id')
                ->where('catalog_import_id', '<>', $import->id)
                ->whereDoesntHave('reservations', fn ($query) => $query->where('status', 'active'))
                ->update([
                    'eligible_flag' => false,
                    'status' => InventoryStatus::Observado->value,
                ]);

            CatalogImport::query()
                ->where('status', 'committed')
                ->where('id', '<>', $import->id)
                ->update(['status' => 'replaced', 'replaced_at' => now()]);

            foreach ($import->rows->where('status', '!=', 'error') as $row) {
                $product = $products[$row->product_code] ??= Product::updateOrCreate(
                    ['product_code' => $row->product_code],
                    $this->productAttributes($row),
                );
                $this->linkIssueReferences($row, $product, $warehouses);

                foreach (self::WAREHOUSE_COLUMNS as $warehouseKey => $warehouseName) {
                    $quantity = (int) ($row->warehouse_quantities[$warehouseKey] ?? 0);

                    if ($quantity <= 0) {
                        continue;
                    }

                    $lotKey = implode('|', [
                        $product->id,
                        $row->lot ?? '',
                        $row->serial ?? '',
                        $row->detail_expiry?->format('Y-m-d') ?? '',
                        $warehouseKey,
                    ]);
                    $aggregatedLots[$lotKey] ??= [
                        'product' => $product,
                        'lot' => $row->lot,
                        'serial' => $row->serial,
                        'expiry' => $row->detail_expiry?->format('Y-m-d'),
                        'location' => $row->location,
                        'warehouse_key' => $warehouseKey,
                        'quantity' => 0,
                    ];
                    $aggregatedLots[$lotKey]['quantity'] += $quantity;
                }
            }

            foreach ($aggregatedLots as $lotData) {
                $warehouse = $warehouses[$lotData['warehouse_key']];
                $status = $this->lotStatus($warehouse, $lotData['expiry'], $lotData['quantity']);
                $product = Product::query()
                    ->lockForUpdate()
                    ->findOrFail($lotData['product']->id);
                $lotIdentity = [
                    'product_id' => $product->id,
                    'lot' => $lotData['lot'],
                    'serial' => $lotData['serial'],
                    'warehouse_id' => $warehouse->id,
                ];
                $matchingLots = InventoryLot::query()
                    ->where($lotIdentity)
                    ->when(
                        $lotData['expiry'] === null,
                        fn ($query) => $query->whereNull('expiry'),
                        fn ($query) => $query->whereDate('expiry', $lotData['expiry']),
                    )
                    ->lockForUpdate()
                    ->get();

                if ($matchingLots->count() > 1) {
                    throw new DomainException('Hay lotes duplicados con la misma identificacion. Corrige el inventario antes de confirmar el catalogo.');
                }

                $existingLot = $matchingLots->first();

                if ($existingLot?->reservations()->where('status', 'active')->exists()) {
                    throw new DomainException('No se puede actualizar desde el catalogo un lote con reservas activas. Concilia primero la reserva y vuelve a importar.');
                }

                $lotAttributes = [
                    'catalog_import_id' => $import->id,
                    'location' => $lotData['location'],
                    'quantity' => $lotData['quantity'],
                    'status' => $status['status'],
                    'eligible_flag' => $status['eligible'],
                    'observations' => 'Importacion '.$import->original_filename,
                ];

                if ($existingLot === null) {
                    InventoryLot::create($lotIdentity + ['expiry' => $lotData['expiry']] + $lotAttributes);
                } else {
                    $existingLot->update($lotAttributes);
                }
            }

            $import->update([
                'status' => 'committed',
                'committed_at' => now(),
            ]);

            $this->auditLogger->record(
                $import->inconsistencies > 0
                    ? 'catalog.import.committed_with_issues'
                    : 'catalog.import.committed',
                $import,
                [],
                [
                    'filename' => $import->original_filename,
                    'products' => $import->products_detected,
                    'lots' => $import->lots_detected,
                    'stock_by_warehouse' => $import->stock_by_warehouse,
                    'inconsistencies' => $import->inconsistencies,
                    'confirmed_negative_inconsistencies' => $confirmNegativeInconsistencies,
                ],
            );

            return $import;
        }, 3);

        return $import->refresh();
    }

    /**
     * @return array{critical: array<int, array{key: string, label: string, count: int, rows: array<int, int>}>, warnings: array<int, array{key: string, label: string, count: int, rows: array<int, int>}>, inconsistencies: array<int, array{key: string, label: string, count: int, rows: array<int, int>}>, critical_total: int, warning_total: int, inconsistency_total: int}
     */
    public function diagnostics(CatalogImport $import): array
    {
        $import->loadMissing('rows');

        $critical = $this->diagnosticBuckets([
            'missing_code' => 'Codigo faltante',
            'missing_lot' => 'Lote faltante',
            'invalid_date' => 'Fecha invalida',
            'negative_quantity' => 'Cantidad negativa',
            'unknown_warehouse' => 'Almacen desconocido',
            'invalid_expiry' => 'Vencimiento invalido',
            'other' => 'Otros errores criticos',
        ]);
        $warnings = $this->diagnosticBuckets([
            'total_mismatch' => 'TOTAL no cuadra',
            'regulatory_expired' => 'Registro sanitario vencido',
            'regulatory_expiry_invalid' => 'Vencimiento de registro sanitario invalido',
            'lot_expired' => 'Lote vencido',
            'empty_location' => 'Ubicacion vacia',
            'other' => 'Otras advertencias',
        ]);
        $inconsistencies = $this->diagnosticBuckets([
            'negative_quantity' => 'Cantidad negativa en almacen',
            'negative_total' => 'TOTAL negativo',
            'other' => 'Otras inconsistencias',
        ]);

        foreach ($import->rows as $row) {
            $this->addDiagnosticMessages($critical, $row->errors ?? [], $row->row_number, 'critical');
            $this->addDiagnosticMessages($warnings, $row->warnings ?? [], $row->row_number, 'warning');
            $this->addDiagnosticIssues($inconsistencies, $row->inconsistencies ?? [], $row->row_number);
        }

        $critical = $this->normalizeDiagnosticRows($critical);
        $warnings = $this->normalizeDiagnosticRows($warnings);

        return [
            'critical' => array_values($critical),
            'warnings' => array_values($warnings),
            'inconsistencies' => array_values($inconsistencies),
            'critical_total' => array_sum(array_column($critical, 'count')),
            'warning_total' => array_sum(array_column($warnings, 'count')),
            'inconsistency_total' => array_sum(array_column($inconsistencies, 'count')),
        ];
    }

    /**
     * @param  array<string, array{key: string, label: string, count: int, rows: array<int, int>}>  $buckets
     * @param  array<int, mixed>  $issues
     */
    private function addDiagnosticIssues(array &$buckets, array $issues, int $rowNumber): void
    {
        foreach ($issues as $issue) {
            $key = is_array($issue) ? (string) ($issue['issue_type'] ?? 'other') : 'other';
            $key = isset($buckets[$key]) ? $key : 'other';
            $buckets[$key]['count']++;
            $buckets[$key]['rows'][] = $rowNumber;
        }
    }

    /**
     * @param  array<string, array{key: string, label: string, count: int, rows: array<int, int>}>  $buckets
     * @return array<string, array{key: string, label: string, count: int, rows: array<int, int>}>
     */
    private function normalizeDiagnosticRows(array $buckets): array
    {
        foreach ($buckets as &$bucket) {
            $bucket['rows'] = collect($bucket['rows'])
                ->map(static fn (int|string $row): int => (int) $row)
                ->unique()
                ->values()
                ->all();
        }
        unset($bucket);

        return $buckets;
    }

    /**
     * @param  array<string, string>  $definitions
     * @return array<string, array{key: string, label: string, count: int, rows: array<int, int>}>
     */
    private function diagnosticBuckets(array $definitions): array
    {
        $buckets = [];

        foreach ($definitions as $key => $label) {
            $buckets[$key] = [
                'key' => $key,
                'label' => $label,
                'count' => 0,
                'rows' => [],
            ];
        }

        return $buckets;
    }

    /**
     * @param  array<string, array{key: string, label: string, count: int, rows: array<int, int>}>  $buckets
     * @param  array<int, mixed>  $messages
     */
    private function addDiagnosticMessages(array &$buckets, array $messages, int $rowNumber, string $severity): void
    {
        foreach ($messages as $message) {
            $key = $this->diagnosticKey((string) $message, $severity);
            $buckets[$key]['count']++;
            $buckets[$key]['rows'][] = $rowNumber;
        }
    }

    private function diagnosticKey(string $message, string $severity): string
    {
        $message = Str::ascii(mb_strtolower($message));

        if ($severity === 'warning') {
            return match (true) {
                str_contains($message, 'total no coincide') => 'total_mismatch',
                str_contains($message, 'registro sanitario esta vencido') => 'regulatory_expired',
                str_contains($message, 'vencimiento del registro sanitario') => 'regulatory_expiry_invalid',
                str_contains($message, 'lote esta vencido') => 'lot_expired',
                str_contains($message, 'ubicacion esta vacia') => 'empty_location',
                default => 'other',
            };
        }

        return match (true) {
            str_contains($message, 'codigo de producto es obligatorio') => 'missing_code',
            str_contains($message, 'lote es obligatorio') => 'missing_lot',
            str_contains($message, 'fecha') && (str_contains($message, 'valida') || str_contains($message, 'invalida')) => 'invalid_date',
            str_contains($message, 'no puede ser negativo') => 'negative_quantity',
            str_contains($message, 'ubicacion del almacen no es reconocida') => 'unknown_warehouse',
            str_contains($message, 'vencimiento del lote no es valido')
                || str_contains($message, 'vencimiento del lote es obligatorio') => 'invalid_expiry',
            default => 'other',
        };
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<string, mixed>
     */
    private function stageRows(CatalogImport $import, array $rows): array
    {
        $nonEmptyRows = array_values(array_filter($rows, fn ($row): bool => is_array($row) && $this->hasValues($row)));

        if ($nonEmptyRows === []) {
            CatalogImportRow::create([
                'catalog_import_id' => $import->id,
                'row_number' => 1,
                'raw_data' => [],
                'status' => 'error',
                'errors' => ['El archivo Excel no contiene filas de datos.'],
                'warnings' => [],
            ]);

            return [
                'status' => 'validated',
                'total_rows' => 0,
                'products_detected' => 0,
                'lots_detected' => 0,
                'critical_errors' => 1,
                'warnings' => 0,
                'inconsistencies' => 0,
                'stock_by_warehouse' => [],
                'error_summary' => [['row' => 0, 'errors' => ['El archivo Excel no contiene filas de datos.'], 'warnings' => []]],
            ];
        }

        $headers = $this->headerMap($nonEmptyRows[0]);
        $headerErrors = collect(self::REQUIRED_HEADERS)
            ->reject(fn (string $header): bool => array_key_exists($header, $headers))
            ->map(fn (string $header): string => 'Falta la columna obligatoria: '.$header.'.')
            ->values()
            ->all();
        $warehouseHeaders = array_intersect(array_keys(self::WAREHOUSE_COLUMNS), array_keys($headers));

        if ($warehouseHeaders === []) {
            $headerErrors[] = 'Falta al menos una columna de stock por almacen.';
        }

        if ($headerErrors !== []) {
            CatalogImportRow::create([
                'catalog_import_id' => $import->id,
                'row_number' => 1,
                'raw_data' => $nonEmptyRows[0],
                'status' => 'error',
                'errors' => $headerErrors,
                'warnings' => [],
            ]);

            return [
                'status' => 'validated',
                'total_rows' => max(0, count($nonEmptyRows) - 1),
                'products_detected' => 0,
                'lots_detected' => 0,
                'critical_errors' => count($headerErrors),
                'warnings' => 0,
                'inconsistencies' => 0,
                'stock_by_warehouse' => [],
                'error_summary' => [['row' => 1, 'errors' => $headerErrors, 'warnings' => []]],
            ];
        }

        $stock = array_fill_keys(array_keys(self::WAREHOUSE_COLUMNS), 0);
        $productCodes = [];
        $lotKeys = [];
        $errorSummary = [];
        $criticalErrors = 0;
        $warnings = 0;
        $inconsistencies = 0;
        $naAccepted = 0;
        $naBlocked = 0;

        foreach (array_slice($nonEmptyRows, 1) as $offset => $rawRow) {
            $normalized = $this->normalizeRow($rawRow, $headers, $offset + 2);
            $row = CatalogImportRow::create([
                'catalog_import_id' => $import->id,
                'row_number' => $normalized['row_number'],
                'raw_data' => $rawRow,
                ...$normalized['data'],
            ]);

            $criticalErrors += count($normalized['errors']);
            $warnings += count($normalized['warnings']);
            $inconsistencies += count($normalized['inconsistencies']);

            foreach ($normalized['inconsistencies'] as $issue) {
                InventoryImportIssue::create([
                    'catalog_import_id' => $import->id,
                    'catalog_import_row_id' => $row->id,
                    'product_code' => $row->product_code,
                    'product_name' => $row->product_name,
                    'lot' => $row->lot,
                    'serial' => $row->serial,
                    'warehouse_key' => $issue['warehouse_key'],
                    'warehouse_name' => $issue['warehouse_name'],
                    'quantity' => $issue['quantity'],
                    'issue_type' => $issue['issue_type'],
                    'message' => $issue['message'],
                    'status' => 'pending',
                ]);
            }
            if ($normalized['data']['expiry_is_na']) {
                if ($normalized['data']['expiry_required']) {
                    $naBlocked++;
                } else {
                    $naAccepted++;
                }
            }
            if ($normalized['errors'] !== [] || $normalized['warnings'] !== []) {
                $errorSummary[] = [
                    'row' => $row->row_number,
                    'errors' => $normalized['errors'],
                    'warnings' => $normalized['warnings'],
                    'inconsistencies' => $normalized['inconsistencies'],
                ];
            }

            if ($row->product_code !== null) {
                $productCodes[$row->product_code] = true;
            }
            if ($row->total_quantity > 0 && $row->lot !== null) {
                $lotKeys[implode('|', [$row->product_code, $row->lot, $row->serial, $row->detail_expiry?->format('Y-m-d')])] = true;
            }

            if ($normalized['errors'] === []) {
                foreach ($stock as $warehouseKey => $value) {
                    $stock[$warehouseKey] += max(0, (int) ($row->warehouse_quantities[$warehouseKey] ?? 0));
                }
            }
        }

        return [
            'status' => 'validated',
            'total_rows' => count($nonEmptyRows) - 1,
            'products_detected' => count($productCodes),
            'lots_detected' => count($lotKeys),
            'critical_errors' => $criticalErrors,
            'warnings' => $warnings,
            'inconsistencies' => $inconsistencies,
            'na_accepted' => $naAccepted,
            'na_blocked' => $naBlocked,
            'stock_by_warehouse' => $stock,
            'error_summary' => $errorSummary,
        ];
    }

    /**
     * @param  array<int, mixed>  $rawRow
     * @param  array<string, int>  $headers
     * @return array{row_number: int, data: array<string, mixed>, errors: array<int, string>, warnings: array<int, string>, inconsistencies: array<int, array<string, mixed>>}
     */
    private function normalizeRow(array $rawRow, array $headers, int $rowNumber): array
    {
        $errors = [];
        $warnings = [];
        $inconsistencies = [];
        $value = fn (string $key): mixed => array_key_exists($key, $headers) ? ($rawRow[$headers[$key]] ?? null) : null;
        $productCode = $this->stringValue($value('product_code'));
        $productName = $this->stringValue($value('product_name'));
        $classification = $this->classifyProduct($productCode, $productName);
        $expiryRequired = $classification === 'consumible';
        $lot = $this->stringValue($value('lot'));
        $serial = $this->stringValue($value('serial'));
        $total = $this->parseQuantity($value('total'), 'TOTAL', $errors, $inconsistencies, 'total');
        $warehouseQuantities = [];

        foreach (array_keys(self::WAREHOUSE_COLUMNS) as $warehouseKey) {
            $warehouseQuantities[$warehouseKey] = $this->parseQuantity(
                $value($warehouseKey),
                self::WAREHOUSE_COLUMNS[$warehouseKey],
                $errors,
                $inconsistencies,
                $warehouseKey,
            );
        }

        $warehouseSum = array_sum($warehouseQuantities);
        $totalWasNegative = collect($inconsistencies)
            ->contains(fn (array $issue): bool => $issue['issue_type'] === 'negative_total');
        if ($total === null || $totalWasNegative) {
            $total = $warehouseSum;
        } elseif ($total !== $warehouseSum) {
            $warnings[] = 'TOTAL no coincide con la suma de cantidades por almacen.';
        }

        if ($productCode === '') {
            $errors[] = 'El codigo de producto es obligatorio.';
        }
        if ($productName === '') {
            $errors[] = 'El nombre de producto es obligatorio.';
        }
        if ($total > 0 && $lot === '') {
            $errors[] = 'El lote es obligatorio cuando existe stock.';
        }

        $detailExpiryValue = $this->stringValue($value('detail_expiry'));
        $expiryIsNa = $this->isNotApplicableExpiry($detailExpiryValue);
        $detailExpiry = $expiryIsNa ? null : $this->parseDate($detailExpiryValue);
        if ($expiryRequired && ($detailExpiryValue === '' || $expiryIsNa || $detailExpiry === null)) {
            $errors[] = 'El vencimiento del lote es obligatorio y debe ser valido para un consumible.';
        } elseif (! $expiryRequired && $detailExpiryValue !== '' && ! $expiryIsNa && $detailExpiry === null) {
            $errors[] = 'El vencimiento del lote no es valido.';
        } elseif ($detailExpiry?->isBefore(today())) {
            $warnings[] = 'El lote esta vencido y se importara como no elegible.';
        }

        $regulatoryExpiry = $this->parseDate($value('regulatory_expiry'));
        if ($this->hasValue($value('regulatory_expiry')) && $regulatoryExpiry === null) {
            $warnings[] = 'El vencimiento del registro sanitario no es valido.';
        } elseif ($regulatoryExpiry?->isBefore(today())) {
            $warnings[] = 'El registro sanitario esta vencido.';
        }

        $location = $this->stringValue($value('location'));
        if ($location !== '' && $this->warehouseKeyFromLocation($location) === null) {
            $errors[] = 'La ubicacion del almacen no es reconocida.';
        } elseif ($location === '' && $total > 0) {
            $warnings[] = 'La ubicacion esta vacia; se usaran las columnas de almacen.';
        }

        return [
            'row_number' => $rowNumber,
            'data' => [
                'product_line' => $this->stringValue($value('product_line')) ?: null,
                'family' => $this->stringValue($value('family')) ?: null,
                'subfamily' => $this->stringValue($value('subfamily')) ?: null,
                'product_code' => $productCode ?: null,
                'product_name' => $productName ?: null,
                'classification' => $classification,
                'expiry_required' => $expiryRequired,
                'expiry_is_na' => $expiryIsNa,
                'regulatory_record' => $this->stringValue($value('regulatory_record')) ?: null,
                'regulatory_expiry' => $regulatoryExpiry?->format('Y-m-d'),
                'lot' => $lot ?: null,
                'serial' => $serial ?: null,
                'detail_expiry' => $detailExpiry?->format('Y-m-d'),
                'location' => $location ?: null,
                'warehouse_quantities' => $warehouseQuantities,
                'total_quantity' => $total,
                'status' => $errors === []
                    ? ($warnings === [] && $inconsistencies === [] ? 'valid' : 'warning')
                    : 'error',
                'errors' => $errors,
                'warnings' => $warnings,
                'inconsistencies' => $inconsistencies,
            ],
            'errors' => $errors,
            'warnings' => $warnings,
            'inconsistencies' => $inconsistencies,
        ];
    }

    /**
     * @param  array<int, mixed>  $headerRow
     * @return array<string, int>
     */
    private function headerMap(array $headerRow): array
    {
        $aliases = [];
        foreach (self::HEADER_ALIASES as $key => $names) {
            foreach ($names as $name) {
                $aliases[$this->normalizeHeader($name)] = $key;
            }
        }

        $headers = [];
        foreach ($headerRow as $index => $header) {
            $normalized = $this->normalizeHeader((string) $header);
            if (isset($aliases[$normalized])) {
                $headers[$aliases[$normalized]] ??= $index;
            }
        }

        return $headers;
    }

    private function normalizeHeader(string $header): string
    {
        $header = Str::ascii(mb_strtolower(trim($header)));

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $header));
    }

    private function classifyProduct(string $productCode, string $productName): string
    {
        $code = Str::ascii(mb_strtolower(trim($productCode)));
        $name = Str::ascii(mb_strtolower(trim($productName)));
        $combined = $code.' '.$name;

        $priorityConsumable = str_contains($name, 'fresa')
            || str_contains($name, 'cuchilla')
            || str_contains($name, 'irrigacion')
            || str_contains($name, 'ird300');

        if ($priorityConsumable) {
            return 'consumible';
        }

        $priorityReusable = str_starts_with($code, 'a-mr8')
            || str_starts_with($code, 'a-e')
            || str_starts_with($code, 'a-c')
            || str_starts_with($code, 'ca')
            || str_starts_with($code, 'pa')
            || str_contains($combined, 'acople');

        if ($priorityReusable) {
            return 'reusable';
        }

        foreach (self::REUSABLE_MARKERS as $marker) {
            if (str_contains($combined, $marker)) {
                return 'reusable';
            }
        }

        foreach (self::CONSUMABLE_MARKERS as $marker) {
            if (str_contains($combined, $marker)) {
                return 'consumible';
            }
        }

        return 'accesorio';
    }

    private function isNotApplicableExpiry(string $value): bool
    {
        $normalized = Str::ascii(mb_strtolower(trim($value)));

        return in_array($normalized, self::NA_EXPIRY_VALUES, true);
    }

    private function stringValue(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function parseQuantity(
        mixed $value,
        string $label,
        array &$errors,
        array &$inconsistencies,
        string $warehouseKey,
    ): ?int {
        if (! $this->hasValue($value)) {
            return null;
        }

        if (is_int($value)) {
            if ($value < 0) {
                $this->addNegativeInconsistency($inconsistencies, $label, $warehouseKey, $value);
            }

            return max(0, $value);
        }

        if (is_float($value)) {
            if ($value < 0) {
                $this->addNegativeInconsistency($inconsistencies, $label, $warehouseKey, (int) $value);
            } elseif (floor($value) !== $value) {
                $errors[] = $label.' debe ser un numero entero.';
            }

            return max(0, (int) $value);
        }

        $text = trim((string) $value);
        if (preg_match('/^-?\d+$/', $text) === 1) {
            $integer = (int) $text;
            if ($integer < 0) {
                $this->addNegativeInconsistency($inconsistencies, $label, $warehouseKey, $integer);
            }

            return max(0, $integer);
        }

        if (preg_match('/^-?\d{1,3}([.,]\d{3})+$/', $text) === 1) {
            $integer = (int) preg_replace('/[.,]/', '', $text);
            if ($integer < 0) {
                $this->addNegativeInconsistency($inconsistencies, $label, $warehouseKey, $integer);
            }

            return max(0, $integer);
        }

        $errors[] = $label.' contiene una cantidad invalida.';

        return 0;
    }

    /**
     * @param  array<int, array<string, mixed>>  $inconsistencies
     */
    private function addNegativeInconsistency(
        array &$inconsistencies,
        string $label,
        string $warehouseKey,
        int $quantity,
    ): void {
        $isTotal = $warehouseKey === 'total';
        $inconsistencies[] = [
            'issue_type' => $isTotal ? 'negative_total' : 'negative_quantity',
            'warehouse_key' => $warehouseKey,
            'warehouse_name' => $isTotal ? 'TOTAL' : (self::WAREHOUSE_COLUMNS[$warehouseKey] ?? $label),
            'quantity' => $quantity,
            'message' => $label.' contiene una cantidad negativa ('.$quantity.'). Se registrara como inconsistencia y no se sumara al stock disponible.',
        ];
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! $this->hasValue($value)) {
            return null;
        }

        try {
            if (is_numeric($value) && (float) $value > 1000) {
                return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $value))->startOfDay();
            }

            foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'd.m.Y'] as $format) {
                try {
                    $date = CarbonImmutable::createFromFormat('!'.$format, trim((string) $value));
                    if ($date !== false) {
                        return $date;
                    }
                } catch (Throwable) {
                }
            }

            return CarbonImmutable::parse((string) $value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    private function hasValues(array $row): bool
    {
        return collect($row)->contains(fn (mixed $value): bool => $this->hasValue($value));
    }

    private function hasValue(mixed $value): bool
    {
        return $value !== null && trim((string) $value) !== '';
    }

    private function warehouseKeyFromLocation(string $location): ?string
    {
        $normalized = $this->normalizeHeader($location);

        foreach (self::WAREHOUSE_COLUMNS as $key => $name) {
            if (str_contains($normalized, $this->normalizeHeader($name))) {
                return $key;
            }
        }

        return match (true) {
            str_contains($normalized, 'principal') => 'principal',
            str_contains($normalized, 'consignacion') => 'consignacion',
            str_contains($normalized, 'desvalorizad') => 'desvalorizado',
            str_contains($normalized, 'ysan') => 'ysan',
            default => null,
        };
    }

    /**
     * @return array<string, Warehouse>
     */
    private function ensureWarehouses(): array
    {
        $definitions = [
            'principal' => ['name' => 'ALMACEN PRINCIPAL', 'type' => WarehouseType::Principal->value, 'lead_time_hours' => 0, 'counts_as_immediate' => true],
            'consignacion' => ['name' => 'ALMACEN CONSIGNACION', 'type' => WarehouseType::Consignacion->value, 'lead_time_hours' => 0, 'counts_as_immediate' => true],
            'desvalorizado' => ['name' => 'ALMACEN DESVALORIZADO', 'type' => WarehouseType::Desvalorizado->value, 'lead_time_hours' => 0, 'counts_as_immediate' => false],
            'ysan' => ['name' => 'ALMACEN YSAN', 'type' => WarehouseType::Ysan->value, 'lead_time_hours' => max(48, (int) config('ops-biomed.ysan_lead_time_hours', 48)), 'counts_as_immediate' => false],
        ];
        $warehouses = [];

        foreach ($definitions as $key => $definition) {
            $warehouses[$key] = Warehouse::updateOrCreate(['name' => $definition['name']], $definition + ['active' => true]);
        }

        return $warehouses;
    }

    /**
     * @return array{status: string, eligible: bool}
     */
    private function lotStatus(Warehouse $warehouse, ?string $expiry, int $quantity): array
    {
        if ($warehouse->type === WarehouseType::Desvalorizado) {
            return ['status' => InventoryStatus::Desvalorizado->value, 'eligible' => false];
        }

        if ($expiry !== null && CarbonImmutable::parse($expiry)->isBefore(today())) {
            return ['status' => InventoryStatus::Vencido->value, 'eligible' => false];
        }

        return [
            'status' => InventoryStatus::Apto->value,
            'eligible' => $quantity > 0 && $warehouse->counts_as_immediate && $warehouse->active,
        ];
    }

    /**
     * @param  array<string, Warehouse>  $warehouses
     */
    private function linkIssueReferences(CatalogImportRow $row, Product $product, array $warehouses): void
    {
        $issues = InventoryImportIssue::query()
            ->where('catalog_import_row_id', $row->id)
            ->get();

        foreach ($issues as $issue) {
            $issue->update([
                'product_id' => $product->id,
                'warehouse_id' => $warehouses[$issue->warehouse_key]->id ?? null,
            ]);
        }
    }

    private function productAttributes(CatalogImportRow $row): array
    {
        return [
            'product_line' => $row->product_line,
            'family' => $row->family,
            'subfamily' => $row->subfamily,
            'normalized_code' => Str::upper((string) preg_replace('/[^A-Za-z0-9]/', '', $row->product_code)),
            'name' => $row->product_name,
            'regulatory_record' => $row->regulatory_record,
            'regulatory_expiry' => $row->regulatory_expiry?->format('Y-m-d'),
            'classification' => $row->classification,
            'expiry_required' => $row->expiry_required,
            'active' => true,
        ];
    }
}
