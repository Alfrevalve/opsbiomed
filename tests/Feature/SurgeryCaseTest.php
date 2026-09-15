<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Models\Doctor;
use App\Models\Institution;
use App\Models\Patient;
use App\Models\SurgeryCase;
use App\Models\SurgeryType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SurgeryCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authorized_user_can_create_and_view_a_surgery_case(): void
    {
        $user = $this->caseManager();
        [$institution, $doctor, $surgeryType] = $this->catalog();
        $scheduledAt = now()->addDay()->format('Y-m-d\TH:i');

        $response = $this->actingAs($user)->post(route('cases.store'), [
            'institution_id' => $institution->id,
            'doctor_id' => $doctor->id,
            'patient_name' => 'Paciente de prueba MR8',
            'surgery_type_id' => $surgeryType->id,
            'scheduled_at' => $scheduledAt,
            'priority' => 'normal',
            'material_requested' => 'Kit MR8 cervical completo',
            'request_origin' => 'whatsapp',
            'notes' => 'Confirmar disponibilidad antes de las 12:00.',
        ]);

        $case = SurgeryCase::query()->firstOrFail();

        $response->assertRedirect(route('cases.show', $case));
        $this->assertSame(CaseStatus::SolicitudRegistrada, $case->status);
        $this->assertDatabaseHas('patients', ['full_name' => 'Paciente de prueba MR8']);

        $this->actingAs($user)
            ->get(route('cases.index'))
            ->assertOk()
            ->assertSee($case->case_code)
            ->assertSee('Paciente de prueba MR8');

        $this->actingAs($user)
            ->get(route('cases.show', $case))
            ->assertOk()
            ->assertSee('Kit MR8 cervical completo')
            ->assertSee('Solicitud registrada');

        $this->actingAs($user)
            ->get(route('dashboard.ops'))
            ->assertOk()
            ->assertSee($case->case_code)
            ->assertSee('Torre de Control Quirurgica')
            ->assertSee('Proximas 48 horas');
    }

    public function test_required_case_fields_are_rejected(): void
    {
        $user = $this->caseManager();

        $this->actingAs($user)
            ->post(route('cases.store'), [])
            ->assertSessionHasErrors([
                'institution_id',
                'doctor_id',
                'patient_name',
                'surgery_type_id',
                'scheduled_at',
                'material_requested',
                'request_origin',
                'notes',
            ]);
    }

    public function test_a_registered_case_can_edit_all_request_fields(): void
    {
        $user = $this->caseManager();
        [$institution, $doctor, $surgeryType] = $this->catalog('original');
        [$newInstitution, $newDoctor, $newSurgeryType] = $this->catalog('updated');
        $patient = Patient::create(['code' => 'PAT-ORIGINAL', 'full_name' => 'Paciente original']);
        $case = SurgeryCase::create($this->caseAttributes($user, $institution, $doctor, $surgeryType, $patient, CaseStatus::SolicitudRegistrada));
        $originalCode = $case->case_code;

        $response = $this->actingAs($user)->patch(route('cases.update', $case), [
            'institution_id' => $newInstitution->id,
            'doctor_id' => $newDoctor->id,
            'patient_name' => 'Paciente actualizado',
            'surgery_type_id' => $newSurgeryType->id,
            'scheduled_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
            'request_origin' => 'correo',
            'material_requested' => 'Kit MR8 actualizado',
            'notes' => 'Observacion actualizada.',
        ]);

        $case->refresh();

        $response->assertRedirect(route('cases.show', $case));
        $this->assertSame($originalCode, $case->case_code);
        $this->assertSame($newInstitution->id, $case->institution_id);
        $this->assertSame($newDoctor->id, $case->doctor_id);
        $this->assertSame($newSurgeryType->id, $case->surgery_type_id);
        $this->assertSame('correo', $case->request_origin);
        $this->assertSame('Kit MR8 actualizado', $case->procedure_name);
        $this->assertSame('Observacion actualizada.', $case->notes);
        $this->assertSame('Paciente actualizado', $case->patient->full_name);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'case.updated',
            'auditable_id' => $case->id,
        ]);
    }

    public function test_a_scheduled_case_only_updates_schedule_material_and_notes(): void
    {
        $user = $this->caseManager();
        [$institution, $doctor, $surgeryType] = $this->catalog();
        $patient = Patient::create(['code' => 'PAT-SCHEDULED', 'full_name' => 'Paciente programado']);
        $case = SurgeryCase::create($this->caseAttributes($user, $institution, $doctor, $surgeryType, $patient, CaseStatus::Programada));

        $response = $this->actingAs($user)->patch(route('cases.update', $case), [
            'institution_id' => 99999,
            'doctor_id' => 99999,
            'patient_name' => 'Paciente manipulado',
            'surgery_type_id' => 99999,
            'request_origin' => 'otro',
            'scheduled_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
            'material_requested' => 'Material programado actualizado',
            'notes' => 'Notas programadas actualizadas.',
        ]);

        $case->refresh();

        $response->assertRedirect(route('cases.show', $case));
        $this->assertSame(CaseStatus::Programada, $case->status);
        $this->assertSame($institution->id, $case->institution_id);
        $this->assertSame($doctor->id, $case->doctor_id);
        $this->assertSame($surgeryType->id, $case->surgery_type_id);
        $this->assertSame('Paciente programado', $case->patient->full_name);
        $this->assertSame('whatsapp', $case->request_origin);
        $this->assertSame('Material programado actualizado', $case->procedure_name);
        $this->assertSame('Notas programadas actualizadas.', $case->notes);
    }

    public function test_a_reserved_case_only_updates_notes(): void
    {
        $user = $this->caseManager();
        [$institution, $doctor, $surgeryType] = $this->catalog();
        $patient = Patient::create(['code' => 'PAT-RESERVED', 'full_name' => 'Paciente reservado']);
        $case = SurgeryCase::create($this->caseAttributes($user, $institution, $doctor, $surgeryType, $patient, CaseStatus::Reservado));
        $originalMaterial = $case->procedure_name;

        $response = $this->actingAs($user)->patch(route('cases.update', $case), [
            'institution_id' => 99999,
            'scheduled_at' => now()->addDays(4)->format('Y-m-d\TH:i'),
            'material_requested' => 'Material no permitido',
            'notes' => 'Observacion reservada actualizada.',
        ]);

        $case->refresh();

        $response->assertRedirect(route('cases.show', $case));
        $this->assertSame(CaseStatus::Reservado, $case->status);
        $this->assertSame($originalMaterial, $case->procedure_name);
        $this->assertSame('Observacion reservada actualizada.', $case->notes);
    }

    public function test_a_closed_case_cannot_be_edited(): void
    {
        $user = $this->caseManager();
        [$institution, $doctor, $surgeryType] = $this->catalog();
        $patient = Patient::create(['code' => 'PAT-CLOSED', 'full_name' => 'Paciente cerrado']);
        $case = SurgeryCase::create($this->caseAttributes($user, $institution, $doctor, $surgeryType, $patient, CaseStatus::Cerrada));
        $originalNotes = $case->notes;

        $response = $this->actingAs($user)->patch(route('cases.update', $case), [
            'notes' => 'Cambio bloqueado.',
        ]);

        $response->assertRedirect(route('cases.show', $case));
        $response->assertSessionHas('error');
        $this->assertSame($originalNotes, $case->refresh()->notes);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'case.updated',
            'auditable_id' => $case->id,
        ]);
    }

    public function test_edit_button_only_appears_for_editable_cases(): void
    {
        $user = $this->caseManager();
        [$institution, $doctor, $surgeryType] = $this->catalog();
        $patient = Patient::create(['code' => 'PAT-BUTTON', 'full_name' => 'Paciente boton']);
        $editableCase = SurgeryCase::create($this->caseAttributes($user, $institution, $doctor, $surgeryType, $patient, CaseStatus::SolicitudRegistrada));
        $lockedCase = SurgeryCase::create($this->caseAttributes($user, $institution, $doctor, $surgeryType, $patient, CaseStatus::Facturada));

        $this->actingAs($user)
            ->get(route('cases.show', $editableCase))
            ->assertOk()
            ->assertSee('Editar solicitud')
            ->assertSee(route('cases.edit', $editableCase));

        $this->actingAs($user)
            ->get(route('cases.show', $lockedCase))
            ->assertOk()
            ->assertDontSee('Editar solicitud');
    }

    private function caseManager(): User
    {
        $permissions = collect(['cases.create', 'cases.view', 'cases.update', 'dashboard.view'])
            ->map(fn (string $permission) => Permission::findOrCreate($permission));
        $role = Role::findOrCreate('phase-1-case-manager');
        $role->syncPermissions($permissions);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function catalog(string $suffix = ''): array
    {
        $label = $suffix === '' ? 'prueba' : $suffix;
        $institution = Institution::create(['name' => 'Institucion '.$label, 'active' => true]);
        $doctor = Doctor::create([
            'name' => 'Medico '.$label,
            'specialty' => 'Neurocirugia',
            'institution_id' => $institution->id,
            'active' => true,
        ]);
        $surgeryType = SurgeryType::create(['code' => 'prueba_'.$label, 'name' => 'Cirugia '.$label, 'active' => true]);

        return [$institution, $doctor, $surgeryType];
    }

    private function caseAttributes(User $user, Institution $institution, Doctor $doctor, SurgeryType $surgeryType, Patient $patient, CaseStatus $status): array
    {
        return [
            'case_code' => 'MR8-TEST-'.strtoupper(substr($status->value, 0, 8)),
            'status' => $status,
            'institution_id' => $institution->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'surgery_type_id' => $surgeryType->id,
            'scheduled_at' => now()->addDay(),
            'priority' => 'normal',
            'procedure_name' => 'Material original',
            'request_origin' => 'whatsapp',
            'notes' => 'Notas originales.',
            'created_by' => $user->id,
        ];
    }
}
