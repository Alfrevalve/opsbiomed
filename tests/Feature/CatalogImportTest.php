<?php

namespace Tests\Feature;

use App\Enums\InventoryStatus;
use App\Models\CatalogImport;
use App\Models\InventoryImportIssue;
use App\Models\InventoryLot;
use App\Models\KitRule;
use App\Models\Product;
use App\Models\SurgeryType;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Catalog\CatalogImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CatalogImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stages_a_valid_file_consolidates_duplicates_and_commits_inventory(): void
    {
        $user = $this->catalogManager();
        $file = $this->catalogFile([
            'MR8,Cervical,Fresa,MR8-VALID-01,Fresa valida,RS-01,31/12/2028,LOTE-01,SER-01,31/12/2028,ALMACEN PRINCIPAL,1,4,5,2,12',
            'MR8,Cervical,Fresa,MR8-VALID-01,Fresa valida,RS-01,31/12/2028,LOTE-01,SER-01,31/12/2028,ALMACEN PRINCIPAL,0,0,2,0,2',
        ]);

        $response = $this->actingAs($user)->post(route('catalog.imports.store'), ['catalog' => $file]);
        $import = CatalogImport::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('catalog.imports.show', $import));
        $this->assertSame(2, $import->total_rows);
        $this->assertSame(1, $import->products_detected);
        $this->assertSame(1, $import->lots_detected);
        $this->assertSame(0, $import->critical_errors);
        $this->assertDatabaseCount('catalog_import_rows', 2);

        $this->actingAs($user)
            ->post(route('catalog.imports.commit', $import))
            ->assertRedirect(route('catalog.imports.show', $import));

        $product = Product::query()->where('product_code', 'MR8-VALID-01')->firstOrFail();
        $principal = Warehouse::query()->where('name', 'ALMACEN PRINCIPAL')->firstOrFail();
        $consignacion = Warehouse::query()->where('name', 'ALMACEN CONSIGNACION')->firstOrFail();
        $ysan = Warehouse::query()->where('name', 'ALMACEN YSAN')->firstOrFail();
        $desvalorizado = Warehouse::query()->where('name', 'ALMACEN DESVALORIZADO')->firstOrFail();

        $this->assertSame('committed', $import->refresh()->status);
        $this->assertDatabaseHas('inventory_lots', ['product_id' => $product->id, 'warehouse_id' => $principal->id, 'quantity' => 7, 'eligible_flag' => true]);
        $this->assertDatabaseHas('inventory_lots', ['product_id' => $product->id, 'warehouse_id' => $consignacion->id, 'quantity' => 1, 'eligible_flag' => true]);
        $this->assertDatabaseHas('inventory_lots', ['product_id' => $product->id, 'warehouse_id' => $ysan->id, 'quantity' => 2, 'eligible_flag' => false]);
        $this->assertDatabaseHas('inventory_lots', ['product_id' => $product->id, 'warehouse_id' => $desvalorizado->id, 'quantity' => 4, 'eligible_flag' => false]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'catalog.import.staged', 'auditable_id' => $import->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'catalog.import.committed', 'auditable_id' => $import->id]);
    }

    public function test_it_accepts_a_real_xlsx_file(): void
    {
        $user = $this->catalogManager();
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            ['Producto Linea', 'Producto Familia', 'Producto Subfamilia', 'Producto Codigo', 'Producto Nombre', 'Registro Sanitario', 'Vencimiento Regsan', 'Detalle Lote', 'Detalle Serie', 'Detalle Vencimiento', 'Detalle Ubicacion', 'ALMACEN CONSIGNACION', 'ALMACEN DESVALORIZAD', 'ALMACEN PRINCIPAL', 'ALMACEN YSAN', 'TOTAL'],
            ['MR8', 'Cervical', 'Fresa', 'MR8-XLSX-01', 'Producto XLSX', 'RS-XLSX', '31/12/2028', 'LOTE-XLSX', 'SER-XLSX', '31/12/2028', 'ALMACEN PRINCIPAL', 0, 0, 5, 0, 5],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'ops-biomed-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $file = new UploadedFile($path, 'catalogo-mr8.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $this->actingAs($user)
            ->post(route('catalog.imports.store'), ['catalog' => $file])
            ->assertRedirect();

        $import = CatalogImport::query()->latest('id')->firstOrFail();
        $this->assertSame(0, $import->critical_errors);
        $this->assertSame(1, $import->products_detected);
        @unlink($path);
    }

    public function test_it_rejects_files_with_missing_columns(): void
    {
        $user = $this->catalogManager();
        $file = $this->catalogFile([
            'MR8,Cervical,Fresa,Producto sin codigo,LOTE-01,31/12/2028,ALMACEN PRINCIPAL,4,4',
        ], 'Producto Linea,Producto Familia,Producto Subfamilia,Producto Nombre,Detalle Lote,Detalle Vencimiento,Detalle Ubicacion,ALMACEN PRINCIPAL,TOTAL');

        $this->actingAs($user)->post(route('catalog.imports.store'), ['catalog' => $file]);
        $import = CatalogImport::query()->latest('id')->firstOrFail();

        $this->assertGreaterThan(0, $import->critical_errors);
        $this->actingAs($user)
            ->post(route('catalog.imports.commit', $import))
            ->assertRedirect(route('catalog.imports.show', $import))
            ->assertSessionHas('error');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_it_detects_negative_quantities_and_invalid_expiry(): void
    {
        $user = $this->catalogManager();
        $file = $this->catalogFile([
            'MR8,Cervical,Fresa,MR8-INVALID-01,Producto invalido,RS-01,fecha-no-valida,LOTE-01,SER-01,fecha-no-valida,ALMACEN PRINCIPAL,-1,-1,-1,0,-1',
        ]);

        $this->actingAs($user)->post(route('catalog.imports.store'), ['catalog' => $file]);
        $import = CatalogImport::query()->latest('id')->firstOrFail();
        $row = $import->rows()->firstOrFail();

        $this->assertGreaterThan(0, $import->critical_errors);
        $this->assertSame(4, $import->inconsistencies);
        $this->assertSame('error', $row->status);
        $this->assertTrue(collect($row->errors)->contains(fn (string $error): bool => str_contains($error, 'vencimiento')));
        $this->assertTrue(collect($row->inconsistencies)->contains(fn (array $issue): bool => str_contains($issue['message'], 'negativa')));
        $this->assertDatabaseCount('inventory_import_issues', 4);
    }

    public function test_import_detail_groups_critical_errors_and_warnings(): void
    {
        $user = $this->catalogManager();
        $file = $this->catalogFile([
            'MR8,Cervical,Fresa,,Sin codigo,RS-01,31/12/2028,LOTE-COD,SER-01,31/12/2028,ALMACEN PRINCIPAL,0,0,1,0,1',
            'MR8,Cervical,Fresa,MR8-DIAG-LOT,Sin lote,RS-01,31/12/2028,,SER-01,31/12/2028,ALMACEN PRINCIPAL,0,0,1,0,1',
            'MR8,Cervical,Fresa,MR8-DIAG-DATE,Fecha invalida,RS-01,31/12/2028,LOTE-DATE,SER-01,fecha-no-valida,ALMACEN PRINCIPAL,0,0,1,0,1',
            'MR8,Cervical,Fresa,MR8-DIAG-QTY,Cantidad negativa,RS-01,31/12/2028,LOTE-QTY,SER-01,31/12/2028,ALMACEN PRINCIPAL,0,0,-1,0,-1',
            'MR8,Cervical,Fresa,MR8-DIAG-WH,Almacen desconocido,RS-01,31/12/2028,LOTE-WH,SER-01,31/12/2028,ALMACEN DESCONOCIDO,0,0,1,0,1',
            'MR8,Cervical,Fresa,MR8-DIAG-WARN,Advertencias,RS-01,01/01/2020,LOTE-WARN,SER-01,31/12/2028,ALMACEN PRINCIPAL,0,0,4,0,5',
            'MR8,Cervical,Fresa,MR8-DIAG-OK,Correcto,RS-01,31/12/2028,LOTE-OK,SER-01,31/12/2028,ALMACEN PRINCIPAL,0,0,1,0,1',
        ]);

        $this->actingAs($user)->post(route('catalog.imports.store'), ['catalog' => $file]);
        $import = CatalogImport::query()->latest('id')->firstOrFail();
        $diagnostics = app(CatalogImportService::class)->diagnostics($import);
        $critical = collect($diagnostics['critical'])->keyBy('key');
        $warnings = collect($diagnostics['warnings'])->keyBy('key');

        $this->assertSame(1, $critical['missing_code']['count']);
        $this->assertSame(1, $critical['missing_lot']['count']);
        $this->assertSame(1, $critical['invalid_expiry']['count']);
        $inconsistencies = collect($diagnostics['inconsistencies'])->keyBy('key');
        $this->assertSame(0, $critical['negative_quantity']['count']);
        $this->assertSame(1, $inconsistencies['negative_quantity']['count']);
        $this->assertSame(1, $inconsistencies['negative_total']['count']);
        $this->assertSame(1, $critical['unknown_warehouse']['count']);
        $this->assertSame(1, $warnings['total_mismatch']['count']);
        $this->assertSame(1, $warnings['regulatory_expired']['count']);

        $this->actingAs($user)
            ->get(route('catalog.imports.show', $import))
            ->assertOk()
            ->assertSee('Errores criticos por tipo')
            ->assertSee('Advertencias por tipo')
            ->assertSee('Inconsistencias por tipo')
            ->assertSee('Inconsistencias')
            ->assertSee('Filtrar estado')
            ->assertSee("data-status='error'", false)
            ->assertSee("data-status='warning'", false)
            ->assertSee("data-status='ok'", false);
    }

    public function test_ysan_is_mapped_as_non_immediate_stock(): void
    {
        $user = $this->catalogManager();
        $file = $this->catalogFile([
            'MR8,Cervical,Fresa,MR8-YSAN-01,Producto YSAN,RS-01,31/12/2028,LOTE-YSAN,SER-01,31/12/2028,ALMACEN YSAN,0,0,0,8,8',
        ]);

        $this->actingAs($user)->post(route('catalog.imports.store'), ['catalog' => $file]);
        $import = CatalogImport::query()->latest('id')->firstOrFail();
        $this->actingAs($user)->post(route('catalog.imports.commit', $import));

        $lot = InventoryLot::query()->where('catalog_import_id', $import->id)->firstOrFail();
        $this->assertSame('ysan', $lot->warehouse->type->value);
        $this->assertSame(48, $lot->warehouse->lead_time_hours);
        $this->assertFalse($lot->warehouse->counts_as_immediate);
        $this->assertFalse($lot->eligible_flag);
    }

    public function test_desvalorizado_is_mapped_as_non_eligible_stock(): void
    {
        $user = $this->catalogManager();
        $file = $this->catalogFile([
            'MR8,Cervical,Fresa,MR8-DESV-01,Producto desvalorizado,RS-01,31/12/2028,LOTE-DESV,SER-01,31/12/2028,ALMACEN DESVALORIZAD,0,5,0,0,5',
        ]);

        $this->actingAs($user)->post(route('catalog.imports.store'), ['catalog' => $file]);
        $import = CatalogImport::query()->latest('id')->firstOrFail();
        $this->actingAs($user)->post(route('catalog.imports.commit', $import));

        $lot = InventoryLot::query()->where('catalog_import_id', $import->id)->firstOrFail();
        $this->assertSame(InventoryStatus::Desvalorizado, $lot->status);
        $this->assertFalse($lot->eligible_flag);
    }

    public function test_dashboard_reads_stock_committed_from_catalog_import(): void
    {
        $user = $this->catalogManager(['inventory.view']);
        $type = SurgeryType::create(['code' => 'catalog-dashboard', 'name' => 'Catalogo dashboard', 'active' => true]);
        KitRule::create([
            'surgery_type_id' => $type->id,
            'length_cm' => 9,
            'diameter_mm' => 1,
            'cut_type' => 'cortante',
            'component_type' => 'fresa',
            'min_qty' => 3,
            'target_qty' => 5,
            'criticality' => 'critica',
            'required' => true,
        ]);
        Product::create([
            'product_code' => 'MR8-DASH-01',
            'name' => 'Producto dashboard',
            'length_cm' => 9,
            'diameter_mm' => 1,
            'cut_type' => 'cortante',
            'component_type' => 'fresa',
            'active' => true,
        ]);

        $file = $this->catalogFile([
            'MR8,Cervical,Fresa,MR8-DASH-01,Producto dashboard,RS-01,31/12/2028,LOTE-DASH,SER-01,31/12/2028,ALMACEN PRINCIPAL,0,0,6,0,6',
        ]);
        $this->actingAs($user)->post(route('catalog.imports.store'), ['catalog' => $file]);
        $import = CatalogImport::query()->latest('id')->firstOrFail();
        $this->actingAs($user)->post(route('catalog.imports.commit', $import));

        $this->actingAs($user)
            ->get(route('dashboard.ops'))
            ->assertOk()
            ->assertSee('Productos criticos bajo minimo')
            ->assertSee('6 unidades netas');
    }

    public function test_reusable_na_expiry_is_accepted(): void
    {
        $user = $this->catalogManager();
        $file = $this->catalogFile([
            'MR8,Equipo,Consola,EM800-NA,Motor EM800,RS-NA,31/12/2028,LOTE-NA,,N/A,ALMACEN PRINCIPAL,0,0,2,0,2',
        ]);

        $this->actingAs($user)->post(route('catalog.imports.store'), ['catalog' => $file]);
        $import = CatalogImport::query()->latest('id')->firstOrFail();
        $row = $import->rows()->firstOrFail();

        $this->assertSame(0, $import->critical_errors);
        $this->assertSame(1, $import->na_accepted);
        $this->assertSame(0, $import->na_blocked);
        $this->assertSame('reusable', $row->classification);
        $this->assertFalse($row->expiry_required);
        $this->assertTrue($row->expiry_is_na);
    }

    public function test_negative_warehouse_quantity_is_an_inconsistency_and_positive_stock_is_imported(): void
    {
        $user = $this->catalogManager(['inventory.view']);
        $file = $this->catalogFile([
            'MR8,Cervical,Accesorio,MR8-NEG-CONS,Producto con ajuste pendiente,RS-NEG,31/12/2028,LOTE-NEG,SER-NEG,31/12/2028,ALMACEN PRINCIPAL,-1,0,2,0,2',
        ]);

        $this->actingAs($user)->post(route('catalog.imports.store'), ['catalog' => $file]);
        $import = CatalogImport::query()->latest('id')->firstOrFail();
        $row = $import->rows()->firstOrFail();

        $this->assertSame(0, $import->critical_errors);
        $this->assertSame(1, $import->inconsistencies);
        $this->assertSame('warning', $row->status);
        $this->assertDatabaseHas('inventory_import_issues', [
            'catalog_import_id' => $import->id,
            'warehouse_key' => 'consignacion',
            'quantity' => -1,
            'issue_type' => 'negative_quantity',
        ]);
        $this->actingAs($user)
            ->get(route('catalog.imports.show', $import))
            ->assertOk()
            ->assertSee('Este archivo contiene cantidades negativas')
            ->assertSee('confirm_negative_inconsistencies', false);

        $this->actingAs($user)
            ->post(route('catalog.imports.commit', $import))
            ->assertRedirect()
            ->assertSessionHasErrors('confirm_negative_inconsistencies');

        $this->assertSame('validated', $import->refresh()->status);
        $this->assertDatabaseCount('inventory_lots', 0);

        $this->actingAs($user)
            ->post(route('catalog.imports.commit', $import), [
                'confirm_negative_inconsistencies' => '1',
            ])
            ->assertRedirect(route('catalog.imports.show', $import));

        $product = Product::query()->where('product_code', 'MR8-NEG-CONS')->firstOrFail();
        $principal = Warehouse::query()->where('name', 'ALMACEN PRINCIPAL')->firstOrFail();
        $consignacion = Warehouse::query()->where('name', 'ALMACEN CONSIGNACION')->firstOrFail();
        $lot = InventoryLot::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $principal->id)
            ->firstOrFail();

        $this->assertSame(2, $lot->quantity);
        $this->assertDatabaseMissing('inventory_lots', [
            'product_id' => $product->id,
            'warehouse_id' => $consignacion->id,
            'quantity' => -1,
        ]);
        $this->assertDatabaseHas('inventory_import_issues', [
            'catalog_import_id' => $import->id,
            'product_id' => $product->id,
            'warehouse_id' => $consignacion->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'catalog.import.committed_with_issues',
            'auditable_id' => $import->id,
        ]);

        $this->actingAs($user)
            ->get(route('inventory.show', $lot))
            ->assertOk()
            ->assertSee('Inconsistencias de importacion');
    }

    public function test_negative_total_is_registered_and_positive_warehouse_stock_is_used(): void
    {
        $user = $this->catalogManager();
        $file = $this->catalogFile([
            'MR8,Cervical,Accesorio,MR8-NEG-TOTAL,Producto total negativo,RS-NEG,31/12/2028,LOTE-TOTAL,SER-TOTAL,31/12/2028,ALMACEN PRINCIPAL,0,0,2,0,-2',
        ]);

        $this->actingAs($user)->post(route('catalog.imports.store'), ['catalog' => $file]);
        $import = CatalogImport::query()->latest('id')->firstOrFail();
        $row = $import->rows()->firstOrFail();

        $this->assertSame(0, $import->critical_errors);
        $this->assertSame(1, $import->inconsistencies);
        $this->assertSame(2, $row->total_quantity);
        $this->assertDatabaseHas('inventory_import_issues', [
            'catalog_import_id' => $import->id,
            'warehouse_key' => 'total',
            'quantity' => -2,
            'issue_type' => 'negative_total',
        ]);

        $this->actingAs($user)->post(route('catalog.imports.commit', $import), [
            'confirm_negative_inconsistencies' => '1',
        ]);

        $product = Product::query()->where('product_code', 'MR8-NEG-TOTAL')->firstOrFail();
        $principal = Warehouse::query()->where('name', 'ALMACEN PRINCIPAL')->firstOrFail();
        $lot = InventoryLot::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $principal->id)
            ->firstOrFail();

        $this->assertSame(2, $lot->quantity);
        $this->assertDatabaseHas('inventory_import_issues', [
            'catalog_import_id' => $import->id,
            'product_id' => $product->id,
            'warehouse_key' => 'total',
            'warehouse_id' => null,
        ]);
    }

    public function test_import_with_only_negative_quantities_can_be_committed_with_confirmation(): void
    {
        $user = $this->catalogManager();
        $file = $this->catalogFile([
            'MR8,Cervical,Accesorio,MR8-NEG-ONLY,Producto solo negativo,RS-NEG,31/12/2028,LOTE-ONLY,SER-ONLY,31/12/2028,ALMACEN PRINCIPAL,-1,0,0,0,-1',
        ]);

        $this->actingAs($user)->post(route('catalog.imports.store'), ['catalog' => $file]);
        $import = CatalogImport::query()->latest('id')->firstOrFail();

        $this->assertSame(0, $import->critical_errors);
        $this->assertSame(2, $import->inconsistencies);

        $this->actingAs($user)
            ->post(route('catalog.imports.commit', $import), [
                'confirm_negative_inconsistencies' => '1',
            ])
            ->assertRedirect();

        $this->assertSame('committed', $import->refresh()->status);
        $this->assertDatabaseHas('products', ['product_code' => 'MR8-NEG-ONLY']);
        $this->assertDatabaseCount('inventory_lots', 0);
        $this->assertSame(2, InventoryImportIssue::query()->where('catalog_import_id', $import->id)->count());
    }

    public function test_dashboard_shows_pending_import_inconsistencies(): void
    {
        $user = $this->catalogManager(['inventory.view']);
        $file = $this->catalogFile([
            'MR8,Cervical,Accesorio,MR8-NEG-DASH,Producto dashboard negativo,RS-NEG,31/12/2028,LOTE-DASH-NEG,SER-DASH-NEG,31/12/2028,ALMACEN PRINCIPAL,-1,0,2,0,2',
        ]);

        $this->actingAs($user)->post(route('catalog.imports.store'), ['catalog' => $file]);
        $import = CatalogImport::query()->latest('id')->firstOrFail();
        $this->actingAs($user)->post(route('catalog.imports.commit', $import), [
            'confirm_negative_inconsistencies' => '1',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard.ops'))
            ->assertOk()
            ->assertSee('Inconsistencias pendientes')
            ->assertSee('Stock negativo');
    }

    public function test_reusable_marker_wins_over_generic_consumable_marker(): void
    {
        $user = $this->catalogManager();
        $file = $this->catalogFile([
            'MR8,Accesorio,Bandeja,BANDEJA-NA,Bandeja de instrumental,RS-NA,31/12/2028,LOTE-BANDEJA,,N/A,ALMACEN PRINCIPAL,0,0,1,0,1',
        ]);

        $this->actingAs($user)->post(route('catalog.imports.store'), ['catalog' => $file]);
        $import = CatalogImport::query()->latest('id')->firstOrFail();
        $row = $import->rows()->firstOrFail();

        $this->assertSame(0, $import->critical_errors);
        $this->assertSame('reusable', $row->classification);
        $this->assertSame(1, $import->na_accepted);
    }

    public function test_consumable_na_expiry_is_blocked(): void
    {
        $user = $this->catalogManager();
        $file = $this->catalogFile([
            'MR8,Consumible,Fresa,MR8-FRESA-NA,Fresa descartable,RS-NA,31/12/2028,LOTE-FRESA-NA,,N/A,ALMACEN PRINCIPAL,0,0,2,0,2',
        ]);

        $this->actingAs($user)->post(route('catalog.imports.store'), ['catalog' => $file]);
        $import = CatalogImport::query()->latest('id')->firstOrFail();
        $row = $import->rows()->firstOrFail();

        $this->assertGreaterThan(0, $import->critical_errors);
        $this->assertSame(0, $import->na_accepted);
        $this->assertSame(1, $import->na_blocked);
        $this->assertSame('consumible', $row->classification);
        $this->assertTrue(collect($row->errors)->contains(fn (string $error): bool => str_contains($error, 'obligatorio')));
    }

    private function catalogManager(array $extraPermissions = []): User
    {
        $permissions = collect(['catalog.import', 'catalog.commit', 'cases.view', 'dashboard.view', ...$extraPermissions])
            ->map(fn (string $permission) => Permission::findOrCreate($permission));
        $role = Role::findOrCreate('phase-3-catalog-manager');
        $role->syncPermissions($permissions);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /**
     * @param  array<int, string>  $dataRows
     */
    private function catalogFile(array $dataRows, ?string $header = null): UploadedFile
    {
        $header ??= 'Producto Linea,Producto Familia,Producto Subfamilia,Producto Codigo,Producto Nombre,Registro Sanitario,Vencimiento Regsan,Detalle Lote,Detalle Serie,Detalle Vencimiento,Detalle Ubicacion,ALMACEN CONSIGNACION,ALMACEN DESVALORIZAD,ALMACEN PRINCIPAL,ALMACEN YSAN,TOTAL';

        return UploadedFile::fake()->createWithContent(
            'catalogo-mr8.csv',
            implode(PHP_EOL, [$header, ...$dataRows]).PHP_EOL,
            'text/csv',
        );
    }
}
