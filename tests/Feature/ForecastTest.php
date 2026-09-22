<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Enums\InventoryStatus;
use App\Models\Institution;
use App\Models\InventoryLot;
use App\Models\KitRule;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\SurgeryCase;
use App\Models\SurgeryType;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\ReplenishmentForecastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ForecastTest extends TestCase
{
    use RefreshDatabase;

    public function test_red_item_generates_urgent_purchase(): void
    {
        $this->forecastManager();
        [, $rule] = $this->ruleFixture('forecast-red');
        $this->lotForRule($rule, ['quantity' => 2]);

        $row = app(ReplenishmentForecastService::class)->summary()['rows']->first();

        $this->assertSame('critical', $row['urgency']);
        $this->assertSame(1, $row['deficit_minimum']);
        $this->assertSame(3, $row['deficit_target']);
        $this->assertSame(3, $row['suggested_purchase']);
        $this->assertStringContainsString('Comprar urgente', $row['action']);
    }

    public function test_yellow_item_generates_purchase_to_target(): void
    {
        $this->forecastManager();
        [, $rule] = $this->ruleFixture('forecast-yellow');
        $this->lotForRule($rule, ['quantity' => 4]);

        $summary = app(ReplenishmentForecastService::class)->summary();
        $row = $summary['rows']->first();

        $this->assertSame('high', $row['urgency']);
        $this->assertSame(0, $row['deficit_minimum']);
        $this->assertSame(1, $row['deficit_target']);
        $this->assertSame(1, $row['suggested_purchase']);
        $this->assertSame(1, $summary['items_high']);
    }

    public function test_green_item_does_not_generate_purchase(): void
    {
        $this->forecastManager();
        [, $rule] = $this->ruleFixture('forecast-green');
        $this->lotForRule($rule, ['quantity' => 5]);

        $summary = app(ReplenishmentForecastService::class)->summary();
        $row = $summary['rows']->first();

        $this->assertSame('normal', $row['urgency']);
        $this->assertSame(0, $row['suggested_purchase']);
        $this->assertSame(1, $summary['items_covered']);
        $this->assertSame(0, $summary['suggested_purchase_total']);
    }

    public function test_ysan_is_support_not_immediate_stock(): void
    {
        $this->forecastManager();
        [, $rule] = $this->ruleFixture('forecast-ysan');
        $this->lotForRule($rule, [
            'quantity' => 8,
            'warehouse' => [
                'name' => 'YSAN forecast '.uniqid(),
                'type' => 'ysan',
                'lead_time_hours' => 48,
                'counts_as_immediate' => false,
            ],
        ]);

        $row = app(ReplenishmentForecastService::class)->summary()['rows']->first();

        $this->assertSame(0, $row['stock_immediate_net']);
        $this->assertSame(8, $row['stock_ysan']);
        $this->assertSame('critical', $row['urgency']);
        $this->assertStringContainsString('YSAN', $row['action']);
    }

    public function test_active_reservations_reduce_immediate_net_stock(): void
    {
        $user = $this->forecastManager();
        [$type, $rule] = $this->ruleFixture('forecast-reserved');
        $lot = $this->lotForRule($rule, ['quantity' => 5]);
        $case = $this->caseForType($type, $user);

        Reservation::create([
            'case_id' => $case->id,
            'inventory_lot_id' => $lot->id,
            'quantity' => 2,
            'status' => 'active',
            'reserved_by' => $user->id,
        ]);

        $row = app(ReplenishmentForecastService::class)->summary()['rows']->first();

        $this->assertSame(2, $row['reserved_active']);
        $this->assertSame(3, $row['stock_immediate_net']);
        $this->assertSame('high', $row['urgency']);
    }

    public function test_devalued_and_expired_stock_is_excluded(): void
    {
        $this->forecastManager();
        [, $rule] = $this->ruleFixture('forecast-ineligible');
        $this->lotForRule($rule, ['quantity' => 20, 'expiry' => now()->subDay()]);
        $this->lotForRule($rule, [
            'quantity' => 20,
            'warehouse' => [
                'name' => 'Desvalorizado forecast '.uniqid(),
                'type' => 'desvalorizado',
                'counts_as_immediate' => false,
            ],
        ]);

        $row = app(ReplenishmentForecastService::class)->summary()['rows']->first();

        $this->assertSame(0, $row['stock_immediate_net']);
    }

    public function test_forecast_route_supports_filters_and_csv_export(): void
    {
        $user = $this->forecastManager();
        [$type, $rule] = $this->ruleFixture('forecast-route');
        $this->lotForRule($rule, ['quantity' => 2]);

        $this->actingAs($user)
            ->get(route('inventory.forecast', ['urgency' => 'critical']))
            ->assertOk()
            ->assertSee('Forecast y reposicion MR8')
            ->assertSee($type->name)
            ->assertSee('Comprar urgente');

        $response = $this->actingAs($user)
            ->get(route('inventory.forecast.export', ['urgency' => 'critical']))
            ->assertDownload('plan-reposicion-mr8.csv');

        $this->assertStringContainsString('MR8-FORECAST', $response->streamedContent());
        $this->assertStringContainsString('Código de producto', $response->streamedContent());
        $this->assertStringContainsString('Producto forecast', $response->streamedContent());
        $this->assertStringNotContainsString('Sin producto equivalente', $response->streamedContent());
    }

    public function test_forecast_export_uses_the_real_product_code_and_name(): void
    {
        $user = $this->forecastManager();
        [, $rule] = $this->ruleFixture('forecast-real-code', 9, 3);
        $this->lotForRule($rule, [
            'product_code' => 'MR8-9BA30',
            'product_name' => 'FRESA CORTANTE 9 CM X 3 MM',
            'family' => 'Fresas',
            'subfamily' => 'Cortantes',
        ]);

        $response = $this->actingAs($user)
            ->get(route('inventory.forecast.export'))
            ->assertDownload('plan-reposicion-mr8.csv');
        $csv = $response->streamedContent();

        $this->assertStringContainsString('MR8-9BA30', $csv);
        $this->assertStringContainsString('FRESA CORTANTE 9 CM X 3 MM', $csv);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $header = str_getcsv(strtok(substr($csv, 3), "\r\n"), ';');
        $this->assertSame(['Tipo de cirugía', 'Código de producto', 'Producto'], array_slice($header, 0, 3));
        $this->assertSame('Observación', $header[18]);
        $this->assertStringNotContainsString('Sin producto equivalente', $csv);
    }

    public function test_forecast_csv_neutralizes_formula_like_product_codes(): void
    {
        $user = $this->forecastManager();
        [, $rule] = $this->ruleFixture('forecast-csv-formula');
        $this->lotForRule($rule, [
            'product_code' => '=HYPERLINK("https://example.invalid","open")',
            'quantity' => 1,
        ]);

        $csv = $this->actingAs($user)
            ->get(route('inventory.forecast.export'))
            ->assertDownload('plan-reposicion-mr8.csv')
            ->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString('"=HYPERLINK', $csv);
    }

    public function test_forecast_marks_a_combination_without_catalog_match_for_review(): void
    {
        $user = $this->forecastManager();
        $type = SurgeryType::create([
            'code' => 'forecast-no-match',
            'name' => 'Cirugia sin equivalencia',
            'active' => true,
        ]);
        KitRule::create([
            'surgery_type_id' => $type->id,
            'length_cm' => 14,
            'diameter_mm' => 3,
            'cut_type' => 'diamantada',
            'component_type' => 'fresa',
            'min_qty' => 3,
            'target_qty' => 5,
            'criticality' => 'critica',
            'required' => true,
        ]);

        $row = app(ReplenishmentForecastService::class)->summary()['rows']->first();

        $this->assertSame('SIN CÓDIGO — REVISAR EQUIVALENCIA', $row['product_code']);
        $this->assertSame('SIN CÓDIGO — REVISAR EQUIVALENCIA', $row['observation']);
    }

    public function test_forecast_does_not_mix_codes_in_one_row(): void
    {
        $this->forecastManager();
        [, $rule] = $this->ruleFixture('forecast-separate-codes');
        $this->lotForRule($rule, ['product_code' => 'MR8-CODE-A', 'quantity' => 2]);
        $this->lotForRule($rule, ['product_code' => 'MR8-CODE-B', 'quantity' => 4]);

        $rows = app(ReplenishmentForecastService::class)->summary()['rows'];

        $this->assertCount(2, $rows);
        $this->assertSame(['MR8-CODE-A', 'MR8-CODE-B'], $rows->pluck('product_code')->sort()->values()->all());
        $this->assertTrue($rows->every(fn (array $row): bool => count($row['product_codes']) === 1));
    }

    public function test_dashboard_shows_forecast_summary_for_operational_roles(): void
    {
        $user = $this->forecastManager();
        [$type, $rule] = $this->ruleFixture('forecast-dashboard');
        $this->lotForRule($rule, ['quantity' => 2]);

        $this->actingAs($user)
            ->get(route('dashboard.ops'))
            ->assertOk()
            ->assertSee('Items para compra urgente')
            ->assertSee('Items bajo objetivo')
            ->assertSee('Cirugias afectadas por falta de stock')
            ->assertSee('Respaldo YSAN disponible')
            ->assertSee($type->name);
    }

    public function test_comercial_cannot_view_internal_forecast(): void
    {
        $permissions = collect(['dashboard.view', 'cases.view', 'inventory.view'])
            ->map(fn (string $permission) => Permission::findOrCreate($permission));
        $role = Role::findOrCreate('Comercial');
        $role->syncPermissions($permissions);
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)
            ->get(route('inventory.forecast'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('inventory.forecast.export'))
            ->assertForbidden();
    }

    private function forecastManager(): User
    {
        $permissions = collect(['dashboard.view', 'cases.view', 'inventory.view'])
            ->map(fn (string $permission) => Permission::findOrCreate($permission));
        $role = Role::findOrCreate('Almacen');
        $role->syncPermissions($permissions);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /**
     * @return array{0: SurgeryType, 1: KitRule}
     */
    private function ruleFixture(string $code, int $length = 9, float $diameter = 2): array
    {
        $type = SurgeryType::create([
            'code' => $code,
            'name' => 'Cirugia '.str_replace('-', ' ', $code),
            'active' => true,
        ]);
        $rule = KitRule::create([
            'surgery_type_id' => $type->id,
            'length_cm' => $length,
            'diameter_mm' => $diameter,
            'cut_type' => 'cortante',
            'component_type' => 'fresa',
            'min_qty' => 3,
            'target_qty' => 5,
            'criticality' => 'critica',
            'required' => true,
            'notes' => 'Combinacion critica de prueba.',
        ]);

        return [$type, $rule];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function lotForRule(KitRule $rule, array $overrides = []): InventoryLot
    {
        $warehouse = Warehouse::create(array_merge([
            'name' => 'Principal forecast '.uniqid(),
            'type' => 'principal',
            'lead_time_hours' => 0,
            'counts_as_immediate' => true,
            'active' => true,
        ], $overrides['warehouse'] ?? []));
        $product = Product::create([
            'product_line' => 'MR8',
            'family' => $overrides['family'] ?? 'Fresas',
            'subfamily' => $overrides['subfamily'] ?? 'Forecast',
            'product_code' => $overrides['product_code'] ?? 'MR8-FORECAST-'.uniqid(),
            'normalized_code' => 'MR8-FORECAST',
            'name' => $overrides['product_name'] ?? 'Producto forecast',
            'length_cm' => $rule->length_cm,
            'diameter_mm' => $rule->diameter_mm,
            'cut_type' => $rule->cut_type,
            'component_type' => $rule->component_type,
            'classification' => 'consumible',
            'expiry_required' => false,
            'tracking_type' => 'lot',
            'active' => true,
        ]);

        return InventoryLot::create(array_merge([
            'product_id' => $product->id,
            'lot' => 'FORECAST-LOT-'.uniqid(),
            'serial' => null,
            'expiry' => now()->addYear(),
            'warehouse_id' => $warehouse->id,
            'quantity' => 15,
            'status' => InventoryStatus::Apto,
            'eligible_flag' => true,
        ], collect($overrides)->except(['warehouse', 'product_code', 'product_name', 'family', 'subfamily'])->all()));
    }

    private function caseForType(SurgeryType $type, User $user): SurgeryCase
    {
        $institution = Institution::create([
            'name' => 'Institucion forecast '.uniqid(),
            'active' => true,
        ]);

        return SurgeryCase::create([
            'case_code' => 'FORECAST-CASE-'.uniqid(),
            'status' => CaseStatus::SolicitudRegistrada,
            'institution_id' => $institution->id,
            'surgery_type_id' => $type->id,
            'scheduled_at' => now()->addDay(),
            'priority' => 'normal',
            'procedure_name' => 'Caso forecast',
            'request_origin' => 'whatsapp',
            'created_by' => $user->id,
        ]);
    }
}
