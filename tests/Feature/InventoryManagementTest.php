<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Enums\InventoryStatus;
use App\Models\Doctor;
use App\Models\Institution;
use App\Models\InventoryAdjustment;
use App\Models\InventoryLot;
use App\Models\Patient;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\SurgeryCase;
use App\Models\SurgeryType;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\StockEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_metadata_can_be_updated_and_is_audited(): void
    {
        $user = $this->userWithPermissions(['inventory.view', 'inventory.update', 'dashboard.view', 'cases.view']);
        $lot = $this->lot();

        $response = $this->actingAs($user)->patch(route('inventory.update', $lot), [
            'name' => 'Producto corregido',
            'subfamily' => 'Nueva subfamilia',
            'regulatory_record' => 'RS-UPDATED',
            'regulatory_expiry' => now()->addYear()->toDateString(),
            'detail_expiry' => now()->addMonths(18)->toDateString(),
            'location' => 'Rack B-02',
            'status' => 'bloqueado',
            'block_reason' => 'Revision tecnica',
            'observations' => 'Observacion actualizada',
            'classification' => 'consumible',
            'expiry_required' => 1,
            'change_reason' => 'Correccion documentada de inventario.',
        ]);

        $response->assertRedirect(route('inventory.show', $lot));
        $this->assertDatabaseHas('products', [
            'id' => $lot->product_id,
            'name' => 'Producto corregido',
            'classification' => 'consumible',
        ]);
        $this->assertDatabaseHas('inventory_lots', [
            'id' => $lot->id,
            'location' => 'Rack B-02',
            'status' => 'bloqueado',
            'block_reason' => 'Revision tecnica',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'inventory.lot.updated',
            'auditable_type' => InventoryLot::class,
            'auditable_id' => $lot->id,
        ]);
    }

    public function test_inventory_list_detail_and_edit_pages_are_available(): void
    {
        $user = $this->userWithPermissions(['inventory.view', 'inventory.update']);
        $lot = $this->lot();

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('Inventario MR8')
            ->assertSee('INV-TEST');
        $this->actingAs($user)
            ->get(route('inventory.show', $lot))
            ->assertOk()
            ->assertSee('Detalle de inventario')
            ->assertSee('Stock total');
        $this->actingAs($user)
            ->get(route('inventory.edit', $lot))
            ->assertOk()
            ->assertSee('Editar datos de inventario')
            ->assertSee('Guardar cambios');
    }

    public function test_inventory_update_rejects_immutable_fields_and_quantity_changes(): void
    {
        $user = $this->userWithPermissions(['inventory.update']);
        $lot = $this->lot(['quantity' => 10]);

        $this->actingAs($user)
            ->patch(route('inventory.update', $lot), [
                'product_code' => 'MANIPULATED',
                'lot' => 'MANIPULATED',
                'serial' => 'MANIPULATED',
                'quantity' => 99,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['product_code', 'lot', 'serial', 'quantity']);

        $this->assertDatabaseHas('products', ['id' => $lot->product_id, 'product_code' => 'INV-TEST']);
        $this->assertDatabaseHas('inventory_lots', ['id' => $lot->id, 'quantity' => 10, 'lot' => 'LOT-TEST']);
    }

    public function test_active_reservation_blocks_critical_inventory_changes_but_allows_observations(): void
    {
        $user = $this->userWithPermissions(['inventory.update']);
        $lot = $this->lot();
        $case = $this->caseFor($user);
        Reservation::create([
            'case_id' => $case->id,
            'inventory_lot_id' => $lot->id,
            'quantity' => 2,
            'status' => 'active',
            'reserved_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->patch(route('inventory.update', $lot), [
                'status' => 'bloqueado',
                'change_reason' => 'Intento de bloqueo con reserva.',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('inventory');

        $this->assertDatabaseHas('inventory_lots', ['id' => $lot->id, 'status' => 'apto']);

        $this->actingAs($user)
            ->patch(route('inventory.update', $lot), ['observations' => 'Nota operativa sin cambio critico.'])
            ->assertRedirect(route('inventory.show', $lot));

        $this->assertDatabaseHas('inventory_lots', [
            'id' => $lot->id,
            'observations' => 'Nota operativa sin cambio critico.',
        ]);
    }

    public function test_expired_lot_cannot_be_released_without_technical_permission(): void
    {
        $user = $this->userWithPermissions(['inventory.update']);
        $lot = $this->lot([
            'expiry' => now()->subDay(),
            'status' => InventoryStatus::Vencido,
        ]);

        $this->actingAs($user)
            ->patch(route('inventory.update', $lot), [
                'status' => InventoryStatus::Apto->value,
                'detail_expiry' => now()->subDay()->toDateString(),
                'change_reason' => 'Intento de liberacion sin aprobacion.',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('inventory');

        $this->assertDatabaseHas('inventory_lots', ['id' => $lot->id, 'status' => 'vencido']);
    }

    public function test_inventory_adjustment_updates_only_stock_and_is_audited(): void
    {
        $user = $this->userWithPermissions(['inventory.adjust', 'inventory.view']);
        $lot = $this->lot(['quantity' => 10]);

        $this->actingAs($user)
            ->post(route('inventory.adjust', $lot), [
                'adjustment_type' => 'conteo_fisico',
                'quantity_adjustment' => 3,
                'reason' => 'Conteo fisico de validacion.',
                'adjusted_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect(route('inventory.show', $lot));

        $this->assertDatabaseHas('inventory_lots', ['id' => $lot->id, 'quantity' => 13]);
        $this->assertDatabaseHas('inventory_adjustments', [
            'inventory_lot_id' => $lot->id,
            'quantity_adjustment' => 3,
            'responsible_user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'inventory.adjustment.created',
            'auditable_type' => InventoryAdjustment::class,
        ]);
    }

    public function test_inventory_adjustment_requires_non_zero_quantity_and_reason(): void
    {
        $user = $this->userWithPermissions(['inventory.adjust']);
        $lot = $this->lot();

        $this->actingAs($user)
            ->post(route('inventory.adjust', $lot), [
                'adjustment_type' => 'conteo_fisico',
                'quantity_adjustment' => 0,
                'reason' => '',
                'adjusted_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['quantity_adjustment', 'reason']);

        $this->assertDatabaseCount('inventory_adjustments', 0);
    }

    public function test_reusable_product_without_expiry_is_eligible_but_consumable_is_not(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'Principal elegibilidad',
            'type' => 'principal',
            'lead_time_hours' => 0,
            'counts_as_immediate' => true,
            'active' => true,
        ]);
        $reusableProduct = Product::create([
            'product_code' => 'REUSABLE-NA',
            'name' => 'Motor reutilizable',
            'classification' => 'reusable',
            'expiry_required' => false,
            'active' => true,
        ]);
        $consumableProduct = Product::create([
            'product_code' => 'FRESA-NA',
            'name' => 'Fresa consumible',
            'classification' => 'consumible',
            'expiry_required' => true,
            'active' => true,
        ]);
        $reusableLot = InventoryLot::create([
            'product_id' => $reusableProduct->id,
            'lot' => 'REUSE-NA',
            'warehouse_id' => $warehouse->id,
            'quantity' => 2,
            'status' => InventoryStatus::Apto,
            'eligible_flag' => true,
        ]);
        $consumableLot = InventoryLot::create([
            'product_id' => $consumableProduct->id,
            'lot' => 'FRESA-NA',
            'warehouse_id' => $warehouse->id,
            'quantity' => 2,
            'status' => InventoryStatus::Apto,
            'eligible_flag' => true,
        ]);

        $eligibility = app(StockEligibilityService::class);
        $this->assertTrue($eligibility->isEligible($reusableLot));
        $this->assertFalse($eligibility->isEligible($consumableLot));
    }

    public function test_dashboard_shows_inventory_alerts(): void
    {
        $user = $this->userWithPermissions(['dashboard.view', 'cases.view', 'inventory.view']);
        $this->lot(['status' => InventoryStatus::Bloqueado, 'eligible_flag' => false]);

        $this->actingAs($user)
            ->get(route('dashboard.ops'))
            ->assertOk()
            ->assertSee('Alertas de inventario')
            ->assertSee('Lotes bloqueados / cuarentena')
            ->assertSee('Falla preventiva')
            ->assertSee('Stock negativo');
    }

    private function userWithPermissions(array $permissions): User
    {
        $permissionModels = collect($permissions)
            ->map(fn (string $permission) => Permission::findOrCreate($permission));
        $role = Role::create(['name' => 'inventory-test-'.uniqid()]);
        $role->syncPermissions($permissionModels);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function lot(array $overrides = []): InventoryLot
    {
        $warehouse = Warehouse::create([
            'name' => 'Principal inventario '.uniqid(),
            'type' => 'principal',
            'lead_time_hours' => 0,
            'counts_as_immediate' => true,
            'active' => true,
        ]);
        $product = Product::create([
            'product_code' => 'INV-TEST',
            'name' => 'Producto inventario',
            'classification' => 'consumible',
            'expiry_required' => true,
            'active' => true,
        ]);

        return InventoryLot::create(array_merge([
            'product_id' => $product->id,
            'lot' => 'LOT-TEST',
            'serial' => 'SER-TEST',
            'expiry' => now()->addYear(),
            'warehouse_id' => $warehouse->id,
            'quantity' => 15,
            'status' => InventoryStatus::Apto,
            'eligible_flag' => true,
            'observations' => 'Inicial',
        ], $overrides));
    }

    private function caseFor(User $user): SurgeryCase
    {
        $institution = Institution::create(['name' => 'Institucion inventario '.uniqid(), 'active' => true]);
        $doctor = Doctor::create([
            'name' => 'Medico inventario '.uniqid(),
            'specialty' => 'Neurocirugia',
            'institution_id' => $institution->id,
            'active' => true,
        ]);
        $patient = Patient::create(['code' => 'PAT-'.uniqid(), 'full_name' => 'Paciente inventario']);
        $type = SurgeryType::create(['code' => 'INV-'.uniqid(), 'name' => 'Cirugia inventario', 'active' => true]);

        return SurgeryCase::create([
            'case_code' => 'CASE-'.uniqid(),
            'status' => CaseStatus::SolicitudRegistrada,
            'institution_id' => $institution->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'surgery_type_id' => $type->id,
            'scheduled_at' => now()->addDay(),
            'priority' => 'normal',
            'procedure_name' => 'Procedimiento inventario',
            'request_origin' => 'whatsapp',
            'notes' => 'Caso de prueba.',
            'created_by' => $user->id,
        ]);
    }
}
