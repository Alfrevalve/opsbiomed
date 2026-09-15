<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Enums\InventoryStatus;
use App\Models\CaseMaterialSent;
use App\Models\CasePreparation;
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

class SurgeryCasePreparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_complete_preparation_and_dispatch_for_every_active_reservation(): void
    {
        $user = $this->userWithRole('Almacen', ['dashboard.view', 'cases.view', 'reservations.create']);
        $case = $this->caseFor($user, CaseStatus::Reservado);
        $reservation = $this->activeReservation($case, $user, 2);

        $this->actingAs($user)
            ->post(route('cases.preparation.store', $case), $this->validPayload($reservation))
            ->assertRedirect(route('cases.control', $case))
            ->assertSessionHasNoErrors();

        $this->assertSame(CaseStatus::Preparacion, $case->refresh()->status);
        $this->assertDatabaseHas('case_preparations', [
            'case_id' => $case->id,
            'institution_confirmed' => true,
            'doctor_confirmed' => true,
            'schedule_confirmed' => true,
            'material_confirmed' => true,
            'documents_confirmed' => true,
            'guide_number' => 'GUIA-PREP-001',
            'prepared_by' => $user->id,
            'dispatched_by' => $user->id,
        ]);
        $this->assertDatabaseHas('case_materials_sent', [
            'case_id' => $case->id,
            'reservation_id' => $reservation->id,
            'inventory_lot_id' => $reservation->inventory_lot_id,
            'quantity' => 2,
            'guide_number' => 'GUIA-PREP-001',
            'sent_by' => $user->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => CasePreparation::class,
            'action' => 'case.preparation.completed',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => CaseMaterialSent::class,
            'action' => 'case.material.dispatched',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => SurgeryCase::class,
            'auditable_id' => $case->id,
            'action' => 'case.status_changed',
        ]);
    }

    public function test_preparation_screen_is_available_to_an_authorized_user(): void
    {
        $user = $this->userWithRole('Almacen', ['cases.view', 'reservations.create']);
        $case = $this->caseFor($user, CaseStatus::Reservado);
        $this->activeReservation($case, $user, 1);

        $this->actingAs($user)
            ->get(route('cases.preparation', $case))
            ->assertOk()
            ->assertSee('Preparacion y despacho a sala')
            ->assertSee($case->case_code)
            ->assertSee('Material reservado');
    }

    public function test_preparation_requires_every_checklist_confirmation(): void
    {
        $user = $this->userWithRole('Almacen', ['cases.view', 'reservations.create']);
        $case = $this->caseFor($user, CaseStatus::Reservado);
        $reservation = $this->activeReservation($case, $user, 1);
        $payload = $this->validPayload($reservation);
        $payload['documents_confirmed'] = 0;

        $this->actingAs($user)
            ->from(route('cases.preparation', $case))
            ->post(route('cases.preparation.store', $case), $payload)
            ->assertRedirect(route('cases.preparation', $case))
            ->assertSessionHasErrors('documents_confirmed');

        $this->assertDatabaseMissing('case_preparations', ['case_id' => $case->id]);
    }

    public function test_preparation_rejects_a_physical_quantity_that_differs_from_the_reservation(): void
    {
        $user = $this->userWithRole('Almacen', ['cases.view', 'reservations.create']);
        $case = $this->caseFor($user, CaseStatus::Reservado);
        $reservation = $this->activeReservation($case, $user, 2);
        $payload = $this->validPayload($reservation);
        $payload['materials'][0]['quantity'] = 1;

        $this->actingAs($user)
            ->from(route('cases.preparation', $case))
            ->post(route('cases.preparation.store', $case), $payload)
            ->assertRedirect(route('cases.preparation', $case))
            ->assertSessionHasErrors('preparation');

        $this->assertDatabaseMissing('case_preparations', ['case_id' => $case->id]);
        $this->assertDatabaseMissing('case_materials_sent', ['case_id' => $case->id]);
    }

    public function test_preparation_requires_a_guide_or_dispatch_evidence(): void
    {
        $user = $this->userWithRole('Almacen', ['cases.view', 'reservations.create']);
        $case = $this->caseFor($user, CaseStatus::Reservado);
        $reservation = $this->activeReservation($case, $user, 1);
        $payload = $this->validPayload($reservation);
        $payload['guide_number'] = '';
        $payload['delivery_evidence_reference'] = '';

        $this->actingAs($user)
            ->from(route('cases.preparation', $case))
            ->post(route('cases.preparation.store', $case), $payload)
            ->assertRedirect(route('cases.preparation', $case))
            ->assertSessionHasErrors('delivery_evidence_reference');
    }

    public function test_case_cannot_enter_the_operating_room_without_a_complete_preoperative_dispatch(): void
    {
        $user = $this->userWithRole('Programador Quirurgico', ['cases.view', 'cases.update']);
        $case = $this->caseFor($user, CaseStatus::Internado);
        $this->activeReservation($case, $user, 1);

        $this->actingAs($user)
            ->post(route('cases.transition', $case), [
                'target_status' => CaseStatus::EnSala->value,
                'override' => 0,
            ])
            ->assertRedirect(route('cases.control', $case))
            ->assertSessionHasErrors('transition');

        $this->assertSame(CaseStatus::Internado, $case->refresh()->status);
    }

    public function test_dashboard_flags_upcoming_cases_without_completed_preoperative_preparation(): void
    {
        $user = $this->userWithRole('Programador Quirurgico', ['dashboard.view', 'cases.view', 'cases.update']);
        $case = $this->caseFor($user, CaseStatus::Reservado);
        $this->activeReservation($case, $user, 1);

        $this->actingAs($user)
            ->get(route('dashboard.ops'))
            ->assertOk()
            ->assertSee('Preoperatorios pendientes')
            ->assertSee($case->case_code)
            ->assertSee('Preoperatorio pendiente');
    }

    public function test_user_without_preparation_access_cannot_open_the_preparation_screen(): void
    {
        $owner = User::factory()->create();
        $case = $this->caseFor($owner, CaseStatus::Reservado);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('cases.preparation', $case))
            ->assertForbidden();
    }

    /** @return array<string, mixed> */
    private function validPayload(Reservation $reservation): array
    {
        return [
            'institution_confirmed' => 1,
            'doctor_confirmed' => 1,
            'schedule_confirmed' => 1,
            'material_confirmed' => 1,
            'documents_confirmed' => 1,
            'guide_number' => 'GUIA-PREP-001',
            'delivery_evidence_reference' => '',
            'notes' => 'Material verificado y entregado a sala.',
            'materials' => [[
                'reservation_id' => $reservation->id,
                'verified' => 1,
                'quantity' => $reservation->quantity,
            ]],
        ];
    }

    private function caseFor(User $user, CaseStatus $status): SurgeryCase
    {
        $institution = Institution::create([
            'name' => 'Institucion preoperatoria '.uniqid(),
            'active' => true,
        ]);
        $doctor = Doctor::create([
            'institution_id' => $institution->id,
            'name' => 'Dr. Preoperatorio '.uniqid(),
            'active' => true,
        ]);
        $patient = Patient::create([
            'code' => 'PREP-'.uniqid(),
            'full_name' => 'Paciente de preparacion',
        ]);
        $surgeryType = SurgeryType::create([
            'code' => 'PREP-'.uniqid(),
            'name' => 'Cirugia de preparacion '.uniqid(),
            'active' => true,
        ]);

        return SurgeryCase::create([
            'case_code' => 'MR8-PREP-'.uniqid(),
            'status' => $status,
            'institution_id' => $institution->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'surgery_type_id' => $surgeryType->id,
            'scheduled_at' => now()->addHour(),
            'priority' => 'normal',
            'procedure_name' => 'Preparacion y despacho MR8',
            'request_origin' => 'whatsapp',
            'notes' => 'Caso de prueba para despacho preoperatorio.',
            'created_by' => $user->id,
        ]);
    }

    private function activeReservation(SurgeryCase $case, User $user, int $quantity): Reservation
    {
        $warehouse = Warehouse::create([
            'name' => 'Principal preparacion '.uniqid(),
            'type' => 'principal',
            'lead_time_hours' => 0,
            'counts_as_immediate' => true,
            'active' => true,
        ]);
        $product = Product::create([
            'product_line' => 'MR8',
            'family' => 'Fresas',
            'subfamily' => 'Preparacion',
            'product_code' => 'MR8-PREP-'.uniqid(),
            'name' => 'Fresa para preparacion',
            'classification' => 'consumible',
            'expiry_required' => false,
            'tracking_type' => 'lot',
            'active' => true,
        ]);
        $lot = InventoryLot::create([
            'product_id' => $product->id,
            'lot' => 'LOT-PREP-'.uniqid(),
            'warehouse_id' => $warehouse->id,
            'quantity' => 8,
            'expiry' => now()->addYear(),
            'status' => InventoryStatus::Apto,
            'eligible_flag' => true,
        ]);

        return Reservation::create([
            'case_id' => $case->id,
            'inventory_lot_id' => $lot->id,
            'quantity' => $quantity,
            'status' => 'active',
            'reserved_by' => $user->id,
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
