<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Enums\InventoryStatus;
use App\Models\Doctor;
use App\Models\Institution;
use App\Models\InventoryLot;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Reservation;
use App\Models\SurgeryCase;
use App\Models\SurgeryType;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Operations\ProductPriceService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductPriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_manager_can_create_general_and_institution_prices(): void
    {
        [$user, $product, $institution] = $this->fixture();
        $payload = $this->pricePayload($product, ['unit_price' => '120.00', 'minimum_price' => '100.00']);

        $response = $this->actingAs($user)->post(route('masters.prices.store'), $payload);
        $generalPrice = ProductPrice::query()->where('product_id', $product->id)->firstOrFail();

        $response->assertRedirect(route('masters.prices.index'));
        $this->assertNull($generalPrice->institution_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'price.created', 'auditable_id' => $generalPrice->id]);

        $institutionResponse = $this->actingAs($user)->post(route('masters.prices.store'), $this->pricePayload($product, [
            'institution_id' => $institution->id,
            'unit_price' => '110.00',
            'minimum_price' => '95.00',
        ]));
        $institutionPrice = ProductPrice::query()->where('institution_id', $institution->id)->firstOrFail();

        $institutionResponse->assertRedirect(route('masters.prices.index'));
        $this->assertDatabaseHas('product_prices', [
            'id' => $institutionPrice->id,
            'institution_id' => $institution->id,
            'unit_price' => 110,
        ]);

        $this->actingAs($user)
            ->patch(route('masters.prices.update', $generalPrice), array_merge($payload, ['active' => 0]))
            ->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['action' => 'price.disabled', 'auditable_id' => $generalPrice->id]);
    }

    public function test_institution_price_has_priority_over_general_price(): void
    {
        [$user, $product, $institution] = $this->fixture();
        ProductPrice::create($this->pricePayload($product, ['unit_price' => '120.00', 'created_by' => $user->id]));
        ProductPrice::create($this->pricePayload($product, [
            'institution_id' => $institution->id,
            'unit_price' => '90.00',
            'created_by' => $user->id,
        ]));

        $price = app(ProductPriceService::class)->current($product->id, $institution->id);

        $this->assertNotNull($price);
        $this->assertSame(90.0, (float) $price->unit_price);
    }

    public function test_expired_price_is_not_used(): void
    {
        [$user, $product, $institution] = $this->fixture();
        ProductPrice::create($this->pricePayload($product, [
            'institution_id' => $institution->id,
            'unit_price' => '120.00',
            'valid_until' => today()->subDay()->toDateString(),
            'created_by' => $user->id,
        ]));

        $this->assertNull(app(ProductPriceService::class)->current($product->id, $institution->id));
    }

    public function test_cost_zero_requires_zero_price_and_observation(): void
    {
        [$user, $product] = $this->fixture();

        $this->actingAs($user)
            ->post(route('masters.prices.store'), $this->pricePayload($product, [
                'price_type' => 'costo_cero',
                'unit_price' => '1.00',
                'observations' => '',
            ]))
            ->assertSessionHasErrors(['unit_price', 'observations']);

        $this->actingAs($user)
            ->post(route('masters.prices.store'), $this->pricePayload($product, [
                'price_type' => 'costo_cero',
                'unit_price' => '0.00',
                'observations' => 'Muestra autorizada para demostracion.',
            ]))
            ->assertRedirect(route('masters.prices.index'));
    }

    public function test_current_price_is_suggested_during_case_valuation(): void
    {
        [$user, $product, $institution, $case, $reservation] = $this->fixture(withCase: true);
        ProductPrice::create($this->pricePayload($product, [
            'institution_id' => $institution->id,
            'unit_price' => '75.50',
            'minimum_price' => '70.00',
            'created_by' => $user->id,
        ]));

        $this->actingAs($user)
            ->get(route('cases.close.create', $case))
            ->assertOk()
            ->assertSee('75.50', false)
            ->assertSee('Monto minimo autorizado: 70.00 PEN', false);

        $this->actingAs($user)
            ->post(route('cases.close', $case), [
                'materials' => [[
                    'reservation_id' => $reservation->id,
                    'used_qty' => 1,
                    'unused_opened_qty' => 0,
                    'returned_qty' => 0,
                    'failure_qty' => 0,
                    'cost_zero' => 0,
                ]],
                'evidence_description' => 'Evidencia de valorizacion automatica.',
            ])
            ->assertRedirect(route('cases.show', $case));

        $this->assertDatabaseHas('case_materials_used', [
            'case_id' => $case->id,
            'unit_price' => 75.50,
            'minimum_unit_price' => 70.00,
            'price_below_minimum' => 0,
        ]);
        $this->assertDatabaseHas('case_valuation_lines', [
            'case_id' => $case->id,
            'unit_price' => 75.50,
            'subtotal' => 75.50,
        ]);
    }

    public function test_manual_price_below_minimum_is_marked(): void
    {
        [$user, $product, $institution, $case, $reservation] = $this->fixture(withCase: true);
        ProductPrice::create($this->pricePayload($product, [
            'institution_id' => $institution->id,
            'unit_price' => '100.00',
            'minimum_price' => '80.00',
            'created_by' => $user->id,
        ]));

        $this->actingAs($user)->post(route('cases.close', $case), [
            'materials' => [[
                'reservation_id' => $reservation->id,
                'used_qty' => 1,
                'unused_opened_qty' => 0,
                'returned_qty' => 0,
                'failure_qty' => 0,
                'unit_price' => '70.00',
                'cost_zero' => 0,
            ]],
            'evidence_description' => 'Precio manual para validar minimo.',
        ])->assertRedirect();

        $this->assertDatabaseHas('case_materials_used', [
            'case_id' => $case->id,
            'unit_price' => 70,
            'minimum_unit_price' => 80,
            'price_below_minimum' => 1,
        ]);
    }

    public function test_viewer_can_read_prices_but_cannot_manage_them(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $product = Product::create([
            'product_line' => 'MR8',
            'family' => 'Fresas',
            'subfamily' => 'Permisos',
            'product_code' => 'MR8-VIEW-'.uniqid(),
            'name' => 'Fresa permisos precios',
            'classification' => 'consumible',
            'expiry_required' => false,
            'tracking_type' => 'lot',
            'active' => true,
        ]);
        $price = ProductPrice::create($this->pricePayload($product));
        $viewer = User::factory()->create(['active' => true]);
        $role = Role::findOrCreate('price-viewer-qa');
        $role->syncPermissions([Permission::findOrCreate('prices.view')]);
        $viewer->assignRole($role);

        $this->actingAs($viewer)->get(route('masters.prices.index'))->assertOk();
        $this->actingAs($viewer)->get(route('masters.prices.create'))->assertForbidden();
        $this->actingAs($viewer)->patch(route('masters.prices.update', $price), [])->assertForbidden();
    }

    /** @return array<string, mixed> */
    private function pricePayload(Product $product, array $overrides = []): array
    {
        return array_merge([
            'product_id' => $product->id,
            'unit_price' => '50.00',
            'minimum_price' => null,
            'currency' => 'PEN',
            'institution_id' => null,
            'doctor_id' => null,
            'price_type' => 'lista',
            'valid_from' => today()->toDateString(),
            'valid_until' => null,
            'active' => 1,
            'observations' => 'Tarifa base MR8.',
        ], $overrides);
    }

    /** @return array<int, mixed> */
    private function fixture(bool $withCase = false): array
    {
        $user = $this->priceManager();
        $institution = Institution::create(['name' => 'Institucion precios '.uniqid(), 'active' => true]);
        $product = Product::create([
            'product_line' => 'MR8',
            'family' => 'Fresas',
            'subfamily' => 'Precios',
            'product_code' => 'MR8-PRICE-'.uniqid(),
            'name' => 'Fresa de prueba precios',
            'classification' => 'consumible',
            'expiry_required' => false,
            'tracking_type' => 'lot',
            'active' => true,
        ]);

        if (! $withCase) {
            return [$user, $product, $institution];
        }

        $type = SurgeryType::create(['code' => 'price_case_'.uniqid(), 'name' => 'Cirugia precios '.uniqid(), 'active' => true]);
        $doctor = Doctor::create(['name' => 'Medico precios '.uniqid(), 'institution_id' => $institution->id, 'active' => true]);
        $case = SurgeryCase::create([
            'case_code' => 'MR8-PRICE-'.uniqid(),
            'status' => CaseStatus::Reservado,
            'institution_id' => $institution->id,
            'doctor_id' => $doctor->id,
            'surgery_type_id' => $type->id,
            'scheduled_at' => now()->addDay(),
            'priority' => 'normal',
            'procedure_name' => 'Kit de prueba de precios',
            'request_origin' => 'correo',
            'notes' => 'Caso para precios.',
            'created_by' => $user->id,
        ]);
        $warehouse = Warehouse::create(['name' => 'Principal precios '.uniqid(), 'type' => 'principal', 'counts_as_immediate' => true, 'active' => true]);
        $lot = InventoryLot::create([
            'product_id' => $product->id,
            'lot' => 'PRICE-LOT-'.uniqid(),
            'warehouse_id' => $warehouse->id,
            'quantity' => 5,
            'expiry' => now()->addYear(),
            'status' => InventoryStatus::Apto,
            'eligible_flag' => true,
        ]);
        $reservation = Reservation::create([
            'case_id' => $case->id,
            'inventory_lot_id' => $lot->id,
            'quantity' => 1,
            'status' => 'active',
            'reserved_by' => $user->id,
        ]);

        return [$user, $product, $institution, $case, $reservation];
    }

    private function priceManager(): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $role = Role::findOrCreate('Almacen');
        $role->syncPermissions([
            Permission::findOrCreate('prices.view'),
            Permission::findOrCreate('prices.manage'),
            Permission::findOrCreate('cases.close'),
            Permission::findOrCreate('cases.view'),
            Permission::findOrCreate('dashboard.view'),
            Permission::findOrCreate('failures.create'),
        ]);
        $user = User::factory()->create(['active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
