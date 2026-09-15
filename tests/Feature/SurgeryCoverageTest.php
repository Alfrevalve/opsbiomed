<?php

namespace Tests\Feature;

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
use App\Services\Inventory\SurgeryCoverageService;
use Database\Seeders\KitRuleSeeder;
use Database\Seeders\SurgeryTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SurgeryCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_coverage_uses_immediate_net_stock_only(): void
    {
        $user = $this->coverageManager();
        [$type, $rule] = $this->ruleFixture('coverage-immediate');
        $principalLot = $this->lotForRule($rule, ['quantity' => 5]);
        $this->lotForRule($rule, [
            'quantity' => 20,
            'warehouse' => [
                'name' => 'YSAN cobertura',
                'type' => 'ysan',
                'counts_as_immediate' => false,
                'lead_time_hours' => 48,
            ],
        ]);
        $this->lotForRule($rule, [
            'quantity' => 20,
            'warehouse' => [
                'name' => 'Desvalorizado cobertura',
                'type' => 'desvalorizado',
                'counts_as_immediate' => false,
            ],
        ]);
        $this->lotForRule($rule, ['quantity' => -10, 'eligible_flag' => true]);
        Reservation::create([
            'case_id' => $this->createCaseId($type, $user),
            'inventory_lot_id' => $principalLot->id,
            'quantity' => 2,
            'status' => 'active',
            'reserved_by' => $user->id,
        ]);

        $summary = app(SurgeryCoverageService::class)->summary();
        $row = $summary['rows']->firstWhere('surgery_type_code', $type->code);

        $this->assertNotNull($row);
        $this->assertSame(5, $row['stock_elegible']);
        $this->assertSame(2, $row['reserved_active']);
        $this->assertSame(3, $row['available_net']);
        $this->assertSame('yellow', $row['risk']);

        $this->actingAs($user)
            ->get(route('inventory.coverage'))
            ->assertOk()
            ->assertSee('Cobertura quirurgica MR8')
            ->assertSee($type->name)
            ->assertSee('Stock elegible inmediato')
            ->assertSee('Considerar YSAN');
    }

    public function test_semaforo_uses_green_yellow_and_red_thresholds(): void
    {
        $this->createCoverageWithQuantity('coverage-green', 5, 7);
        $this->createCoverageWithQuantity('coverage-yellow', 4, 9);
        $this->createCoverageWithQuantity('coverage-red', 2, 10);

        $summary = app(SurgeryCoverageService::class)->summary();

        $this->assertSame('green', $summary['rows']->firstWhere('surgery_type_code', 'coverage-green')['risk']);
        $this->assertSame('yellow', $summary['rows']->firstWhere('surgery_type_code', 'coverage-yellow')['risk']);
        $this->assertSame('red', $summary['rows']->firstWhere('surgery_type_code', 'coverage-red')['risk']);
    }

    public function test_craneo_requires_3_4_and_5_mm(): void
    {
        $this->seed(SurgeryTypeSeeder::class);
        $this->seed(KitRuleSeeder::class);
        $type = SurgeryType::query()->where('code', 'craneo')->with('kitRules')->firstOrFail();

        $fresaRules = $type->kitRules->where('component_type', 'fresa');
        $diameters = $fresaRules->pluck('diameter_mm')->map(fn ($diameter): float => (float) $diameter)->unique()->sort()->values()->all();
        $lengths = $fresaRules->pluck('length_cm')->map(fn ($length): float => (float) $length)->unique()->sort()->values()->all();

        $this->assertSame([3.0, 4.0, 5.0], $diameters);
        $this->assertSame([7.0, 9.0, 10.0], $lengths);
        $this->assertSame(['cortante', 'diamantada'], $fresaRules->pluck('cut_type')->unique()->sort()->values()->all());
        $this->assertTrue($type->kitRules->contains(fn (KitRule $rule): bool => $rule->component_type === 'cuchilla' && $rule->cut_type === 'iniciadora'));
        $this->assertTrue($type->kitRules->contains(fn (KitRule $rule): bool => $rule->component_type === 'cuchilla' && $rule->cut_type === 'F2'));
        $this->assertTrue($type->kitRules->contains(fn (KitRule $rule): bool => $rule->component_type === 'cuchilla' && $rule->cut_type === 'F3'));
    }

    public function test_cervical_requires_1_2_and_3_mm(): void
    {
        $this->seed(SurgeryTypeSeeder::class);
        $this->seed(KitRuleSeeder::class);
        $type = SurgeryType::query()->where('code', 'cervical')->with('kitRules')->firstOrFail();
        $fresaRules = $type->kitRules->where('component_type', 'fresa');

        $this->assertSame([1.0, 2.0, 3.0], $fresaRules->pluck('diameter_mm')->map(fn ($diameter): float => (float) $diameter)->unique()->sort()->values()->all());
        $this->assertSame([9.0, 10.0], $fresaRules->pluck('length_cm')->map(fn ($length): float => (float) $length)->unique()->sort()->values()->all());
        $this->assertSame(4, $fresaRules->where('diameter_mm', 2)->count());
    }

    public function test_endoscopic_non_nasal_does_not_require_4_or_5_mm(): void
    {
        $this->seed(SurgeryTypeSeeder::class);
        $this->seed(KitRuleSeeder::class);
        $type = SurgeryType::query()->where('code', 'endoscopica_no_nasal')->with('kitRules')->firstOrFail();
        $fresaRules = $type->kitRules->where('component_type', 'fresa');

        $this->assertSame([2.2, 3.0], $fresaRules->pluck('diameter_mm')->map(fn ($diameter): float => (float) $diameter)->unique()->sort()->values()->all());
        $this->assertFalse($fresaRules->contains(fn (KitRule $rule): bool => in_array((float) $rule->diameter_mm, [4.0, 5.0], true)));
    }

    public function test_dashboard_shows_coverage_breakdown(): void
    {
        $user = $this->coverageManager();
        [$type, $rule] = $this->ruleFixture('coverage-dashboard');
        $this->lotForRule($rule, ['quantity' => 2]);

        $this->actingAs($user)
            ->get(route('dashboard.ops'))
            ->assertOk()
            ->assertSee('Combinaciones en rojo')
            ->assertSee('Combinaciones en amarillo')
            ->assertSee('Tipos con cobertura completa')
            ->assertSee('Tipos en riesgo')
            ->assertSee('Productos criticos bajo minimo')
            ->assertSee('Productos bajo objetivo')
            ->assertSee('Torre de Control Quirurgica')
            ->assertSee('Sistema operativo')
            ->assertSee('Siguiente accion recomendada')
            ->assertSee($type->name);
    }

    private function coverageManager(): User
    {
        $permissions = collect(['dashboard.view', 'cases.view', 'inventory.view'])
            ->map(fn (string $permission) => Permission::findOrCreate($permission));
        $role = Role::findOrCreate('coverage-manager');
        $role->syncPermissions($permissions);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /**
     * @return array{0: SurgeryType, 1: KitRule}
     */
    private function ruleFixture(string $code, int $length = 9): array
    {
        $type = SurgeryType::create([
            'code' => $code,
            'name' => 'Cirugia '.str_replace('-', ' ', $code),
            'active' => true,
        ]);
        $rule = KitRule::create([
            'surgery_type_id' => $type->id,
            'length_cm' => $length,
            'diameter_mm' => 2,
            'cut_type' => 'cortante',
            'component_type' => 'fresa',
            'min_qty' => 3,
            'target_qty' => 5,
            'criticality' => 'critica',
            'required' => true,
        ]);

        return [$type, $rule];
    }

    private function createCoverageWithQuantity(string $code, int $quantity, int $length): void
    {
        [, $rule] = $this->ruleFixture($code, $length);
        $this->lotForRule($rule, ['quantity' => $quantity]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function lotForRule(KitRule $rule, array $overrides = []): InventoryLot
    {
        $warehouse = Warehouse::create(array_merge([
            'name' => 'Principal '.uniqid(),
            'type' => 'principal',
            'lead_time_hours' => 0,
            'counts_as_immediate' => true,
            'active' => true,
        ], $overrides['warehouse'] ?? []));
        $product = Product::create([
            'product_line' => 'MR8',
            'family' => 'Fresas',
            'subfamily' => 'Cobertura',
            'product_code' => 'COV-'.uniqid(),
            'normalized_code' => 'COV',
            'name' => 'Producto cobertura',
            'length_cm' => $rule->length_cm,
            'diameter_mm' => $rule->diameter_mm,
            'cut_type' => $rule->cut_type,
            'component_type' => $rule->component_type,
            'classification' => 'reusable',
            'expiry_required' => false,
            'tracking_type' => 'lot',
            'active' => true,
        ]);

        return InventoryLot::create(array_merge([
            'product_id' => $product->id,
            'lot' => 'COV-LOT-'.uniqid(),
            'serial' => null,
            'expiry' => now()->addYear(),
            'warehouse_id' => $warehouse->id,
            'quantity' => 15,
            'status' => InventoryStatus::Apto,
            'eligible_flag' => true,
        ], collect($overrides)->except('warehouse')->all()));
    }

    private function createCaseId(SurgeryType $type, User $user): int
    {
        $institution = Institution::create([
            'name' => 'Institucion cobertura '.uniqid(),
            'active' => true,
        ]);

        return (int) SurgeryCase::create([
            'case_code' => 'COV-CASE-'.uniqid(),
            'status' => 'solicitud_registrada',
            'institution_id' => $institution->id,
            'surgery_type_id' => $type->id,
            'scheduled_at' => now()->addDay(),
            'priority' => 'normal',
            'procedure_name' => 'Cobertura',
            'request_origin' => 'whatsapp',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ])->id;
    }
}
