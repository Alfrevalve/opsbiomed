<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Enums\InventoryStatus;
use App\Models\AuditLog;
use App\Models\CaseMaterialSent;
use App\Models\CasePreparation;
use App\Models\CaseReconciliation;
use App\Models\CaseReturn;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SurgeryCaseTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_advance_to_the_next_operational_status_and_it_is_audited(): void
    {
        $user = $this->userWithRole('Programador Quirurgico', ['dashboard.view', 'cases.view', 'cases.update']);
        $case = $this->caseFor($user, CaseStatus::SolicitudRegistrada);

        $this->actingAs($user)
            ->post(route('cases.transition', $case), [
                'target_status' => CaseStatus::Programada->value,
                'observation' => 'Agenda confirmada con la institucion.',
                'override' => 0,
            ])
            ->assertRedirect(route('cases.control', $case))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('surgery_cases', [
            'id' => $case->id,
            'status' => CaseStatus::Programada->value,
        ]);

        $audit = AuditLog::query()
            ->where('auditable_type', SurgeryCase::class)
            ->where('auditable_id', $case->id)
            ->where('action', 'case.status_changed')
            ->firstOrFail();

        $this->assertSame(CaseStatus::SolicitudRegistrada->value, $audit->before['status']);
        $this->assertSame(CaseStatus::Programada->value, $audit->after['new_status']);
        $this->assertSame($user->id, $audit->after['user_id']);
        $this->assertSame('Agenda confirmada con la institucion.', $audit->after['observation']);
    }

    public function test_user_without_transition_permission_cannot_update_a_case_status(): void
    {
        $owner = User::factory()->create();
        $case = $this->caseFor($owner, CaseStatus::SolicitudRegistrada);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('cases.transition', $case), [
                'target_status' => CaseStatus::Programada->value,
                'override' => 0,
            ])
            ->assertForbidden();

        $this->assertSame(CaseStatus::SolicitudRegistrada, $case->refresh()->status);
    }

    public function test_user_without_override_role_cannot_skip_operational_states(): void
    {
        $user = $this->userWithRole('Programador Quirurgico', ['cases.view', 'cases.update']);
        $case = $this->caseFor($user, CaseStatus::SolicitudRegistrada);

        $this->actingAs($user)
            ->post(route('cases.transition', $case), [
                'target_status' => CaseStatus::Preparacion->value,
                'observation' => 'Intento de salto.',
                'override' => 0,
            ])
            ->assertRedirect(route('cases.control', $case))
            ->assertSessionHasErrors('transition');

        $this->assertSame(CaseStatus::SolicitudRegistrada, $case->refresh()->status);
    }

    public function test_case_cannot_move_to_operating_room_without_an_active_reservation(): void
    {
        $user = $this->userWithRole('Programador Quirurgico', ['cases.view', 'cases.update']);
        $case = $this->caseFor($user, CaseStatus::Internado);

        $this->actingAs($user)
            ->post(route('cases.transition', $case), [
                'target_status' => CaseStatus::EnSala->value,
                'override' => 0,
            ])
            ->assertRedirect(route('cases.control', $case))
            ->assertSessionHasErrors('transition');

        $this->assertSame(CaseStatus::Internado, $case->refresh()->status);
    }

    public function test_case_cannot_be_marked_as_surgery_finished_outside_the_room_or_surgery(): void
    {
        $administrator = $this->userWithRole('Administrador', ['cases.view', 'cases.update', 'cases.close']);
        $case = $this->caseFor($administrator, CaseStatus::Internado);

        $this->actingAs($administrator)
            ->post(route('cases.transition', $case), [
                'target_status' => CaseStatus::PendienteCierre->value,
                'observation' => 'Intento de cierre anticipado.',
                'override' => 1,
            ])
            ->assertRedirect(route('cases.control', $case))
            ->assertSessionHasErrors('transition');

        $this->assertSame(CaseStatus::Internado, $case->refresh()->status);
    }

    public function test_cancellation_requires_an_observation_and_is_audited(): void
    {
        $user = $this->userWithRole('Programador Quirurgico', ['cases.view', 'cases.update']);
        $case = $this->caseFor($user, CaseStatus::Programada);

        $this->actingAs($user)
            ->post(route('cases.transition', $case), [
                'target_status' => CaseStatus::Cancelado->value,
                'override' => 0,
            ])
            ->assertRedirect(route('cases.control', $case))
            ->assertSessionHasErrors('transition');

        $this->actingAs($user)
            ->post(route('cases.transition', $case), [
                'target_status' => CaseStatus::Cancelado->value,
                'observation' => 'La institucion confirmo la cancelacion de la cirugia.',
                'override' => 0,
            ])
            ->assertRedirect(route('cases.control', $case));

        $this->assertSame(CaseStatus::Cancelado, $case->refresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => SurgeryCase::class,
            'auditable_id' => $case->id,
            'action' => 'case.status_changed',
        ]);
    }

    public function test_late_return_inspection_requires_an_observation_before_conciliation(): void
    {
        $user = $this->userWithRole('Jefe de Linea', ['cases.view', 'cases.update', 'cases.close']);
        $case = $this->caseFor($user, CaseStatus::Cerrado);
        $lot = $this->addActiveReservation($case, $user);
        $return = CaseReturn::create([
            'case_id' => $case->id,
            'inventory_lot_id' => $lot->id,
            'returned_qty' => 1,
            'condition' => 'liberado',
            'inspected_by' => $user->id,
            'inspected_at' => now(),
        ]);
        $return->forceFill(['created_at' => now()->subHours(25)])->save();
        CaseReconciliation::create([
            'case_id' => $case->id,
            'status' => 'conciliado',
            'completed_by' => $user->id,
            'completed_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('cases.transition', $case), [
                'target_status' => CaseStatus::Conciliacion->value,
                'override' => 0,
            ])
            ->assertRedirect(route('cases.control', $case))
            ->assertSessionHasErrors('transition');

        $this->actingAs($user)
            ->post(route('cases.transition', $case), [
                'target_status' => CaseStatus::Conciliacion->value,
                'observation' => 'La inspeccion se retraso por entrega tardia del lote.',
                'override' => 0,
            ])
            ->assertRedirect(route('cases.control', $case));

        $this->assertSame(CaseStatus::Conciliacion, $case->refresh()->status);
    }

    public function test_total_closure_is_blocked_when_the_case_has_open_administrative_or_operational_work(): void
    {
        $administrator = $this->userWithRole('Administrador', ['dashboard.view', 'cases.view', 'cases.update', 'cases.close']);
        $case = $this->caseFor($administrator, CaseStatus::Facturacion);

        $this->actingAs($administrator)
            ->post(route('cases.transition', $case), [
                'target_status' => CaseStatus::Facturada->value,
                'override' => 0,
            ])
            ->assertRedirect(route('cases.control', $case))
            ->assertSessionHasErrors('transition');

        $this->assertSame(CaseStatus::Facturacion, $case->refresh()->status);
    }

    public function test_administrator_can_apply_an_observed_override_and_it_is_audited(): void
    {
        $administrator = $this->userWithRole('Administrador', ['dashboard.view', 'cases.view', 'cases.update', 'cases.close']);
        $case = $this->caseFor($administrator, CaseStatus::Facturacion);

        $this->actingAs($administrator)
            ->post(route('cases.transition', $case), [
                'target_status' => CaseStatus::Facturada->value,
                'override' => 1,
            ])
            ->assertRedirect(route('cases.control', $case))
            ->assertSessionHasErrors('transition');

        $this->actingAs($administrator)
            ->post(route('cases.transition', $case), [
                'target_status' => CaseStatus::Facturada->value,
                'observation' => 'Cierre administrativo autorizado durante el piloto.',
                'override' => 1,
            ])
            ->assertRedirect(route('cases.control', $case))
            ->assertSessionHasNoErrors();

        $audit = AuditLog::query()
            ->where('auditable_type', SurgeryCase::class)
            ->where('auditable_id', $case->id)
            ->where('action', 'case.status_changed')
            ->firstOrFail();

        $this->assertSame(CaseStatus::Facturada, $case->refresh()->status);
        $this->assertTrue($audit->after['override']);
        $this->assertSame('Cierre administrativo autorizado durante el piloto.', $audit->after['observation']);
    }

    public function test_control_and_dashboard_reflect_the_new_operational_status(): void
    {
        $user = $this->userWithRole('Programador Quirurgico', ['dashboard.view', 'cases.view', 'cases.update']);
        $case = $this->caseFor($user, CaseStatus::Internado);
        $this->addActiveReservation($case, $user);
        $this->completePreparation($case, $user);

        $this->actingAs($user)
            ->post(route('cases.transition', $case), [
                'target_status' => CaseStatus::EnSala->value,
                'override' => 0,
            ])
            ->assertRedirect(route('cases.control', $case));

        $this->actingAs($user)
            ->get(route('cases.control', $case))
            ->assertOk()
            ->assertSee('Avance operativo')
            ->assertSee('Estado actual: En sala')
            ->assertSee('Internada')
            ->assertSee('En sala');

        $this->actingAs($user)
            ->get(route('dashboard.ops'))
            ->assertOk()
            ->assertSee($case->case_code)
            ->assertSee('En sala');
    }

    private function caseFor(User $user, CaseStatus $status): SurgeryCase
    {
        $institution = Institution::create([
            'name' => 'Institucion transicion '.uniqid(),
            'active' => true,
        ]);
        $doctor = Doctor::create([
            'institution_id' => $institution->id,
            'name' => 'Dr. Transicion '.uniqid(),
            'active' => true,
        ]);
        $patient = Patient::create([
            'code' => 'TRANS-'.uniqid(),
            'full_name' => 'Paciente de transicion',
        ]);
        $surgeryType = SurgeryType::create([
            'code' => 'TRANS-'.uniqid(),
            'name' => 'Cirugia de transicion '.uniqid(),
            'active' => true,
        ]);

        return SurgeryCase::create([
            'case_code' => 'MR8-TRANS-'.uniqid(),
            'status' => $status,
            'institution_id' => $institution->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'surgery_type_id' => $surgeryType->id,
            'scheduled_at' => now()->addHour(),
            'priority' => 'normal',
            'procedure_name' => 'Control de flujo MR8',
            'request_origin' => 'whatsapp',
            'notes' => 'Caso de prueba para transiciones operativas.',
            'created_by' => $user->id,
        ]);
    }

    private function addActiveReservation(SurgeryCase $case, User $user): InventoryLot
    {
        $warehouse = Warehouse::create([
            'name' => 'Principal transicion '.uniqid(),
            'type' => 'principal',
            'lead_time_hours' => 0,
            'counts_as_immediate' => true,
            'active' => true,
        ]);
        $product = Product::create([
            'product_line' => 'MR8',
            'family' => 'Fresas',
            'subfamily' => 'Transicion',
            'product_code' => 'MR8-TRANS-'.uniqid(),
            'name' => 'Fresa para transicion',
            'classification' => 'consumible',
            'expiry_required' => false,
            'tracking_type' => 'lot',
            'active' => true,
        ]);
        $lot = InventoryLot::create([
            'product_id' => $product->id,
            'lot' => 'LOT-TRANS-'.uniqid(),
            'warehouse_id' => $warehouse->id,
            'quantity' => 5,
            'expiry' => now()->addYear(),
            'status' => InventoryStatus::Apto,
            'eligible_flag' => true,
        ]);

        Reservation::create([
            'case_id' => $case->id,
            'inventory_lot_id' => $lot->id,
            'quantity' => 1,
            'status' => 'active',
            'reserved_by' => $user->id,
        ]);

        return $lot;
    }

    private function completePreparation(SurgeryCase $case, User $user): void
    {
        $reservation = Reservation::query()
            ->where('case_id', $case->id)
            ->where('status', 'active')
            ->firstOrFail();

        CasePreparation::create([
            'case_id' => $case->id,
            'institution_confirmed' => true,
            'doctor_confirmed' => true,
            'schedule_confirmed' => true,
            'material_confirmed' => true,
            'documents_confirmed' => true,
            'guide_number' => 'GUIA-TRANS-001',
            'prepared_by' => $user->id,
            'prepared_at' => now(),
            'dispatched_by' => $user->id,
            'dispatched_at' => now(),
        ]);

        CaseMaterialSent::create([
            'case_id' => $case->id,
            'reservation_id' => $reservation->id,
            'inventory_lot_id' => $reservation->inventory_lot_id,
            'quantity' => $reservation->quantity,
            'guide_number' => 'GUIA-TRANS-001',
            'sent_at' => now(),
            'sent_by' => $user->id,
        ]);
    }

    /** @param list<string> $permissions */
    private function userWithRole(string $roleName, array $permissions): User
    {
        $role = Role::findOrCreate($roleName);
        $role->syncPermissions(
            collect($permissions)->map(fn (string $permission): Permission => Permission::findOrCreate($permission)),
        );
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
