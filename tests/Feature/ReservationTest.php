<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Enums\InventoryStatus;
use App\Models\Doctor;
use App\Models\Institution;
use App\Models\InventoryLot;
use App\Models\KitRule;
use App\Models\Patient;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\SurgeryCase;
use App\Models\SurgeryType;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_eligible_lot_can_be_reserved_and_is_audited(): void
    {
        $user = $this->reservationManager();
        [$case, $lot] = $this->fixture($user, ['quantity' => 15]);

        $this->actingAs($user)
            ->get(route('cases.reserve.create', $case))
            ->assertOk()
            ->assertSee($lot->product->product_code)
            ->assertSee('Disponible neto');

        $response = $this->actingAs($user)->post(route('cases.reserve', $case), [
            'inventory_lot_id' => $lot->id,
            'quantity' => 4,
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertRedirect(route('cases.show', $case));
        $this->assertDatabaseHas('reservations', [
            'case_id' => $case->id,
            'inventory_lot_id' => $lot->id,
            'quantity' => 4,
            'status' => 'active',
            'reserved_by' => $user->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'reservation.created',
            'auditable_type' => Reservation::class,
        ]);
        $this->assertSame(CaseStatus::Reservado, $case->refresh()->status);
    }

    public function test_a_reservation_cannot_exceed_net_available_quantity(): void
    {
        $user = $this->reservationManager();
        [$case, $lot] = $this->fixture($user, ['quantity' => 5]);
        Reservation::create([
            'case_id' => $case->id,
            'inventory_lot_id' => $lot->id,
            'quantity' => 3,
            'status' => 'active',
            'reserved_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('cases.reserve', $case), ['inventory_lot_id' => $lot->id, 'quantity' => 3, 'idempotency_key' => (string) Str::uuid()])
            ->assertRedirect(route('cases.reserve.create', $case))
            ->assertSessionHasErrors('quantity');

        $this->assertSame(1, Reservation::query()->where('case_id', $case->id)->count());
    }

    public function test_expired_lots_cannot_be_reserved(): void
    {
        $user = $this->reservationManager();
        [$case, $lot] = $this->fixture($user, ['expiry' => now()->subDay()]);

        $this->actingAs($user)
            ->get(route('cases.reserve.create', $case))
            ->assertOk()
            ->assertDontSee($lot->product->product_code);

        $this->actingAs($user)
            ->post(route('cases.reserve', $case), ['inventory_lot_id' => $lot->id, 'quantity' => 1, 'idempotency_key' => (string) Str::uuid()])
            ->assertRedirect(route('cases.reserve.create', $case))
            ->assertSessionHasErrors('quantity');
    }

    public function test_blocked_lots_cannot_be_reserved(): void
    {
        $user = $this->reservationManager();
        [$case, $lot] = $this->fixture($user, ['status' => InventoryStatus::Bloqueado]);

        $this->actingAs($user)
            ->post(route('cases.reserve', $case), ['inventory_lot_id' => $lot->id, 'quantity' => 1, 'idempotency_key' => (string) Str::uuid()])
            ->assertRedirect(route('cases.reserve.create', $case))
            ->assertSessionHasErrors('quantity');
    }

    public function test_lots_with_an_open_preventive_failure_cannot_be_reserved(): void
    {
        $user = $this->reservationManager();
        [$case, $lot] = $this->fixture($user);
        DB::table('failures')->insert([
            'inventory_lot_id' => $lot->id,
            'severity' => 'alta',
            'status' => 'reportada',
            'preventive_block' => true,
            'description' => 'Falla de prueba.',
            'reported_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('cases.reserve', $case), ['inventory_lot_id' => $lot->id, 'quantity' => 1, 'idempotency_key' => (string) Str::uuid()])
            ->assertRedirect(route('cases.reserve.create', $case))
            ->assertSessionHasErrors('quantity');
    }

    public function test_devalued_and_ysan_warehouses_are_not_immediate_stock(): void
    {
        $user = $this->reservationManager();
        [$case, $devaluedLot] = $this->fixture($user, [
            'warehouse' => ['name' => 'Desvalorizado de prueba', 'type' => 'desvalorizado', 'counts_as_immediate' => false],
        ]);
        [, $ysanLot] = $this->fixture($user, [
            'warehouse' => ['name' => 'YSAN de prueba', 'type' => 'ysan', 'lead_time_hours' => 48, 'counts_as_immediate' => false],
        ]);

        $response = $this->actingAs($user)->get(route('cases.reserve.create', $case));

        $response->assertOk()->assertDontSee($devaluedLot->product->product_code);

        $otherCase = SurgeryCase::query()->latest('id')->firstOrFail();
        $this->actingAs($user)
            ->get(route('cases.reserve.create', $otherCase))
            ->assertOk()
            ->assertDontSee($ysanLot->product->product_code);
    }

    public function test_zero_and_negative_quantities_are_rejected(): void
    {
        $user = $this->reservationManager();
        [$case, $lot] = $this->fixture($user);

        foreach ([0, -1] as $quantity) {
            $this->actingAs($user)
                ->post(route('cases.reserve', $case), ['inventory_lot_id' => $lot->id, 'quantity' => $quantity, 'idempotency_key' => (string) Str::uuid()])
                ->assertRedirect()
                ->assertSessionHasErrors('quantity');
        }

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_replaying_the_same_reservation_request_does_not_duplicate_stock_or_audit(): void
    {
        $user = $this->reservationManager();
        [$case, $lot] = $this->fixture($user, ['quantity' => 5]);
        $requestData = [
            'inventory_lot_id' => $lot->id,
            'quantity' => 2,
            'idempotency_key' => (string) Str::uuid(),
        ];

        $this->actingAs($user)->post(route('cases.reserve', $case), $requestData)
            ->assertRedirect(route('cases.show', $case));
        $this->actingAs($user)->post(route('cases.reserve', $case), $requestData)
            ->assertRedirect(route('cases.show', $case));

        $this->assertDatabaseCount('reservations', 1);
        $this->assertSame(2, (int) Reservation::query()->sum('quantity'));
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'reservation.created')->count());
    }

    public function test_two_cases_cannot_reserve_more_than_the_last_immediate_unit(): void
    {
        $firstUser = $this->reservationManager();
        $secondUser = $this->reservationManager();
        [$firstCase, $lot] = $this->fixture($firstUser, ['quantity' => 1]);
        $secondCase = SurgeryCase::create([
            'case_code' => 'MR8-RES-COMPETE-'.uniqid(),
            'status' => CaseStatus::SolicitudRegistrada,
            'institution_id' => $firstCase->institution_id,
            'doctor_id' => $firstCase->doctor_id,
            'patient_id' => $firstCase->patient_id,
            'surgery_type_id' => $firstCase->surgery_type_id,
            'scheduled_at' => now()->addDays(2),
            'priority' => 'normal',
            'procedure_name' => 'Kit MR8 de competencia',
            'request_origin' => 'whatsapp',
            'created_by' => $secondUser->id,
        ]);

        $this->actingAs($firstUser)->post(route('cases.reserve', $firstCase), [
            'inventory_lot_id' => $lot->id,
            'quantity' => 1,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect(route('cases.show', $firstCase));

        $this->actingAs($secondUser)->post(route('cases.reserve', $secondCase), [
            'inventory_lot_id' => $lot->id,
            'quantity' => 1,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect(route('cases.reserve.create', $secondCase))->assertSessionHasErrors('quantity');

        $this->assertSame(1, (int) Reservation::query()->where('inventory_lot_id', $lot->id)->sum('quantity'));
        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_dashboard_shows_stock_risk_and_incomplete_reservation_metrics(): void
    {
        $user = $this->reservationManager();
        [$case] = $this->fixture($user, ['quantity' => 2]);
        $this->fixture($user, [
            'quantity' => 12,
            'length_cm' => 10,
            'product_code' => 'MR8-TEST-YELLOW',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard.ops'))
            ->assertOk()
            ->assertSee('Reservas activas')
            ->assertSee('Combinaciones en rojo')
            ->assertSee('Combinaciones en amarillo')
            ->assertSee('Reservas incompletas')
            ->assertSee($case->case_code);
    }

    private function reservationManager(): User
    {
        $permissions = collect(['cases.view', 'reservations.create', 'dashboard.view', 'inventory.view'])
            ->map(fn (string $permission) => Permission::findOrCreate($permission));
        $role = Role::findOrCreate('phase-2-reservation-manager');
        $role->syncPermissions($permissions);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /**
     * @return array{0: SurgeryCase, 1: InventoryLot}
     */
    private function fixture(User $user, array $overrides = []): array
    {
        $institution = Institution::create(['name' => 'Institucion reserva '.uniqid(), 'active' => true]);
        $doctor = Doctor::create([
            'name' => 'Medico reserva '.uniqid(),
            'specialty' => 'Neurocirugia',
            'institution_id' => $institution->id,
            'active' => true,
        ]);
        $surgeryType = SurgeryType::create(['code' => 'reserva_'.uniqid(), 'name' => 'Cirugia reserva '.uniqid(), 'active' => true]);
        $patient = Patient::create(['code' => 'PAT-'.uniqid(), 'full_name' => 'Paciente reserva '.uniqid()]);
        $case = SurgeryCase::create([
            'case_code' => 'MR8-RES-'.uniqid(),
            'status' => CaseStatus::SolicitudRegistrada,
            'institution_id' => $institution->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'surgery_type_id' => $surgeryType->id,
            'scheduled_at' => now()->addDay(),
            'priority' => 'normal',
            'procedure_name' => 'Kit MR8 de prueba',
            'request_origin' => 'whatsapp',
            'notes' => 'Caso para prueba de reservas.',
            'created_by' => $user->id,
        ]);

        $rule = KitRule::create([
            'surgery_type_id' => $surgeryType->id,
            'length_cm' => $overrides['length_cm'] ?? 9,
            'diameter_mm' => $overrides['diameter_mm'] ?? 1,
            'cut_type' => $overrides['cut_type'] ?? 'cortante',
            'component_type' => 'fresa',
            'min_qty' => 3,
            'target_qty' => 5,
            'criticality' => 'critica',
            'required' => true,
        ]);

        $warehouseAttributes = $overrides['warehouse'] ?? ['name' => 'Principal reserva '.uniqid(), 'type' => 'principal', 'counts_as_immediate' => true];
        $warehouse = Warehouse::create($warehouseAttributes + ['lead_time_hours' => 0, 'active' => true]);
        $product = Product::create([
            'product_line' => 'MR8',
            'family' => 'Fresas',
            'subfamily' => 'Prueba',
            'product_code' => $overrides['product_code'] ?? 'MR8-TEST-'.uniqid(),
            'normalized_code' => 'MR8-TEST',
            'name' => 'Producto de reserva',
            'length_cm' => $rule->length_cm,
            'diameter_mm' => $rule->diameter_mm,
            'cut_type' => $rule->cut_type,
            'component_type' => $rule->component_type,
            'tracking_type' => 'lot',
            'active' => true,
        ]);
        $lot = InventoryLot::create([
            'product_id' => $product->id,
            'lot' => 'LOTE-'.uniqid(),
            'serial' => null,
            'expiry' => $overrides['expiry'] ?? now()->addYear(),
            'warehouse_id' => $warehouse->id,
            'quantity' => $overrides['quantity'] ?? 15,
            'status' => $overrides['status'] ?? InventoryStatus::Apto,
            'eligible_flag' => true,
        ]);

        return [$case, $lot->load(['product', 'warehouse']), $rule];
    }
}
