<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Enums\InventoryStatus;
use App\Models\AuditLog;
use App\Models\CaseMaterialSent;
use App\Models\CaseMaterialUsed;
use App\Models\Doctor;
use App\Models\Institution;
use App\Models\InventoryLot;
use App\Models\Patient;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\SurgeryCase;
use App\Models\SurgeryType;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Audit\AuditLogger;
use App\Services\Inventory\ReservationReleaseService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReservationReleaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_jefe_de_linea_and_almacen_can_release_an_eligible_reservation(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        foreach (['Administrador', 'Jefe de Linea', 'Almacen'] as $roleName) {
            $user = $this->userWithRole($roleName);
            [$case, $lot, $reservation] = $this->reservationFixture($user);
            $key = (string) Str::uuid();

            $this->actingAs($user)
                ->post(route('reservations.release', $reservation), [
                    'reason' => 'Cambio de programacion del caso.',
                    'idempotency_key' => $key,
                ])
                ->assertRedirect(route('cases.control', $case));

            $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'released']);
            $this->assertSame(12, (int) $lot->refresh()->quantity);
            $audit = AuditLog::query()->where('action', 'reservation.released')->latest('id')->firstOrFail();
            $this->assertSame('active', $audit->before['status']);
            $this->assertSame('released', $audit->after['status']);
            $this->assertSame('Cambio de programacion del caso.', $audit->after['reason']);
            $this->assertSame($key, $audit->after['idempotency_key']);
            $this->assertSame($user->id, $audit->user_id);
            $this->assertNotEmpty($audit->ip);
        }
    }

    public function test_confirmation_screen_lists_material_and_direct_release_eligibility(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = $this->userWithRole('Administrador');
        [$case, $lot, $reservation] = $this->reservationFixture($user);

        $this->actingAs($user)
            ->get(route('reservations.release.form', $reservation))
            ->assertOk()
            ->assertSee('Liberacion total de reserva')
            ->assertSee($case->case_code)
            ->assertSee($lot->product->product_code)
            ->assertSee('Liberacion directa elegible');
    }

    public function test_instrumentista_and_users_without_permission_cannot_release_reservations(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $instrumentista = $this->userWithRole('Instrumentista');
        [$case, , $reservation] = $this->reservationFixture($instrumentista);

        $this->actingAs($instrumentista)
            ->post(route('reservations.release', $reservation), [
                'reason' => 'No tiene permiso para liberar.',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'active']);
    }

    public function test_nonexistent_reservation_returns_not_found(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = $this->userWithRole('Administrador');

        $this->actingAs($user)->get('/reservations/999999/release')->assertNotFound();
    }

    public function test_released_reservation_is_not_changed_again_when_submitted_with_a_new_key(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = $this->userWithRole('Administrador');
        [$case, $lot, $reservation] = $this->reservationFixture($user);
        $this->release($user, $reservation, (string) Str::uuid());

        $this->actingAs($user)
            ->post(route('reservations.release', $reservation), [
                'reason' => 'Segundo intento manual.',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('release');

        $this->assertSame(12, (int) $lot->refresh()->quantity);
        $this->assertSame('released', $reservation->refresh()->status);
        $this->assertSame(1, AuditLog::query()->where('action', 'reservation.released')->count());
    }

    public function test_consumed_reservation_cannot_be_released(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = $this->userWithRole('Administrador');
        [$case, $lot, $reservation] = $this->reservationFixture($user);
        CaseMaterialUsed::create([
            'case_id' => $case->id,
            'reservation_id' => $reservation->id,
            'inventory_lot_id' => $lot->id,
            'reserved_qty' => $reservation->quantity,
            'used_qty' => 1,
        ]);

        $this->actingAs($user)
            ->post(route('reservations.release', $reservation), [
                'reason' => 'Intento de liberar consumo.',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('release');

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'active']);
    }

    public function test_reservation_for_a_closed_case_cannot_be_released(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = $this->userWithRole('Administrador');
        [$case, , $reservation] = $this->reservationFixture($user, CaseStatus::Cerrado);

        $this->actingAs($user)
            ->post(route('reservations.release', $reservation), [
                'reason' => 'Intento sobre caso cerrado.',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('release');

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'active']);
    }

    public function test_dispatched_or_interned_material_requires_return_and_inspection(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = $this->userWithRole('Administrador');
        [$case, $lot, $reservation] = $this->reservationFixture($user);
        CaseMaterialSent::create([
            'case_id' => $case->id,
            'reservation_id' => $reservation->id,
            'inventory_lot_id' => $lot->id,
            'quantity' => $reservation->quantity,
            'sent_at' => now(),
            'sent_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('reservations.release.form', $reservation))
            ->assertOk()
            ->assertSee('Liberacion directa bloqueada')
            ->assertSee('devolucion y la inspeccion postoperatoria')
            ->assertDontSee('Liberar reserva completa');

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'active']);

        [$internedCase, , $internedReservation] = $this->reservationFixture($user, CaseStatus::Internado);
        $this->actingAs($user)
            ->post(route('reservations.release', $internedReservation), [
                'reason' => 'Intento de liberar material internado.',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('release');
        $this->assertSame(CaseStatus::Internado, $internedCase->refresh()->status);
        $this->assertDatabaseHas('reservations', ['id' => $internedReservation->id, 'status' => 'active']);
    }

    public function test_empty_reason_is_rejected_without_mutating_the_reservation(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = $this->userWithRole('Administrador');
        [, , $reservation] = $this->reservationFixture($user);

        $this->actingAs($user)
            ->post(route('reservations.release', $reservation), [
                'reason' => '',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('reason');

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'active']);
        $this->assertDatabaseCount('reservation_release_operations', 0);
    }

    public function test_replaying_the_same_release_request_is_idempotent(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = $this->userWithRole('Administrador');
        [$case, $lot, $reservation] = $this->reservationFixture($user);
        $payload = [
            'reason' => 'Reserva duplicada por reintento.',
            'idempotency_key' => (string) Str::uuid(),
        ];

        $this->actingAs($user)->post(route('reservations.release', $reservation), $payload)
            ->assertRedirect(route('cases.control', $case));
        $this->actingAs($user)->post(route('reservations.release', $reservation), $payload)
            ->assertRedirect(route('cases.control', $case));

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'released']);
        $this->assertSame(12, (int) $lot->refresh()->quantity);
        $this->assertDatabaseCount('reservation_release_operations', 1);
        $this->assertSame(1, AuditLog::query()->where('action', 'reservation.released')->count());
        $this->assertDatabaseCount('trace_events', 1);
    }

    public function test_audit_failure_rolls_back_release_operation_reservation_and_trace_event(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = $this->userWithRole('Administrador');
        [$case, $lot, $reservation] = $this->reservationFixture($user);
        $auditLogger = $this->mock(AuditLogger::class);
        $auditLogger->shouldReceive('record')->once()->andThrow(new \RuntimeException('Audit failure'));

        try {
            app(ReservationReleaseService::class)->release(
                $reservation,
                'Prueba de rollback auditado.',
                (string) Str::uuid(),
                $user,
            );
            $this->fail('Se esperaba que fallara el registro de auditoria.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit failure', $exception->getMessage());
        }

        $this->assertSame('active', $reservation->refresh()->status);
        $this->assertSame(12, (int) $lot->refresh()->quantity);
        $this->assertDatabaseCount('reservation_release_operations', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('trace_events', 0);
    }

    public function test_cancel_case_releases_all_eligible_reservations_in_one_operation(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = $this->userWithRole('Jefe de Linea');
        [$case, $lot, $firstReservation] = $this->reservationFixture($user, CaseStatus::Reservado);
        $secondReservation = Reservation::create([
            'case_id' => $case->id,
            'inventory_lot_id' => $lot->id,
            'quantity' => 2,
            'status' => 'active',
            'reserved_by' => $user->id,
        ]);
        $payload = [
            'reason' => 'Institucion solicito cancelacion.',
            'idempotency_key' => (string) Str::uuid(),
            'confirmation' => '1',
        ];

        $this->actingAs($user)
            ->post(route('cases.cancel', $case), $payload)
            ->assertRedirect(route('cases.control', $case));

        $this->assertSame(CaseStatus::Cancelado, $case->refresh()->status);
        $this->assertSame('released', $firstReservation->refresh()->status);
        $this->assertSame('released', $secondReservation->refresh()->status);
        $this->assertSame(12, (int) $lot->refresh()->quantity);
        $this->assertSame(0, (int) Reservation::query()->where('inventory_lot_id', $lot->id)->where('status', 'active')->sum('quantity'));
        $this->assertSame(2, AuditLog::query()->where('action', 'reservation.released')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'case.cancelled_with_reservations', 'auditable_id' => $case->id]);
        $this->assertSame(1, AuditLog::query()->where('action', 'case.cancelled_with_reservations')->count());
    }

    public function test_cancel_case_with_one_ineligible_reservation_rolls_back_every_change(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = $this->userWithRole('Almacen');
        [$case, $lot, $firstReservation] = $this->reservationFixture($user, CaseStatus::Reservado);
        $secondReservation = Reservation::create([
            'case_id' => $case->id,
            'inventory_lot_id' => $lot->id,
            'quantity' => 2,
            'status' => 'active',
            'reserved_by' => $user->id,
        ]);
        CaseMaterialSent::create([
            'case_id' => $case->id,
            'reservation_id' => $secondReservation->id,
            'inventory_lot_id' => $lot->id,
            'quantity' => $secondReservation->quantity,
            'sent_at' => now(),
            'sent_by' => $user->id,
        ]);
        $this->actingAs($user)
            ->get(route('cases.cancel.form', $case))
            ->assertOk()
            ->assertSee('Se liberara')
            ->assertSee('Bloqueada')
            ->assertSee('devolucion y la inspeccion postoperatoria')
            ->assertDontSee('Confirmar cancelacion y liberacion total');

        $this->actingAs($user)
            ->post(route('cases.cancel', $case), [
                'reason' => 'Cancelacion con material despachado.',
                'idempotency_key' => (string) Str::uuid(),
                'confirmation' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('cancellation');

        $this->assertSame(CaseStatus::Reservado, $case->refresh()->status);
        $this->assertSame('active', $firstReservation->refresh()->status);
        $this->assertSame('active', $secondReservation->refresh()->status);
        $this->assertSame(12, (int) $lot->refresh()->quantity);
        $this->assertDatabaseCount('reservation_release_operations', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_cancel_case_requires_visible_confirmation_and_reason(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = $this->userWithRole('Administrador');
        [$case] = $this->reservationFixture($user);

        $this->actingAs($user)
            ->post(route('cases.cancel', $case), [
                'reason' => '',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['reason', 'confirmation']);

        $this->assertSame(CaseStatus::SolicitudRegistrada, $case->refresh()->status);
    }

    private function release(User $user, Reservation $reservation, string $key): void
    {
        $this->actingAs($user)->post(route('reservations.release', $reservation), [
            'reason' => 'Liberacion de prueba.',
            'idempotency_key' => $key,
        ])->assertRedirect();
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create(['active' => true]);
        $user->assignRole(Role::findByName($roleName));

        return $user;
    }

    /** @return array{SurgeryCase, InventoryLot, Reservation} */
    private function reservationFixture(User $user, CaseStatus $status = CaseStatus::SolicitudRegistrada): array
    {
        $institution = Institution::create(['name' => 'Institucion liberacion '.Str::uuid(), 'active' => true]);
        $doctor = Doctor::create([
            'name' => 'Medico liberacion '.Str::uuid(),
            'specialty' => 'Neurocirugia',
            'institution_id' => $institution->id,
            'active' => true,
        ]);
        $patient = Patient::create(['code' => 'PAT-'.Str::upper(Str::random(12)), 'full_name' => 'Paciente de prueba']);
        $surgeryType = SurgeryType::create(['code' => 'REL-'.Str::upper(Str::random(10)), 'name' => 'Cirugia de liberacion '.Str::random(8), 'active' => true]);
        $case = SurgeryCase::create([
            'case_code' => 'REL-'.Str::upper(Str::random(12)),
            'status' => $status,
            'institution_id' => $institution->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'surgery_type_id' => $surgeryType->id,
            'scheduled_at' => now()->addDay(),
            'priority' => 'normal',
            'procedure_name' => 'Prueba de liberacion',
            'request_origin' => 'otro',
            'created_by' => $user->id,
        ]);
        $warehouse = Warehouse::create([
            'name' => 'Principal liberacion '.Str::uuid(),
            'type' => 'principal',
            'lead_time_hours' => 0,
            'counts_as_immediate' => true,
            'active' => true,
        ]);
        $product = Product::create([
            'product_code' => 'MR8-REL-'.Str::upper(Str::random(10)),
            'name' => 'Fresa demo de liberacion',
            'classification' => 'consumible',
            'expiry_required' => false,
            'active' => true,
        ]);
        $lot = InventoryLot::create([
            'product_id' => $product->id,
            'lot' => 'LOTE-REL-'.Str::upper(Str::random(8)),
            'warehouse_id' => $warehouse->id,
            'quantity' => 12,
            'status' => InventoryStatus::Apto,
            'eligible_flag' => true,
        ]);
        $reservation = Reservation::create([
            'case_id' => $case->id,
            'inventory_lot_id' => $lot->id,
            'quantity' => 3,
            'status' => 'active',
            'reserved_by' => $user->id,
        ]);

        return [$case, $lot->load(['product', 'warehouse']), $reservation];
    }
}
