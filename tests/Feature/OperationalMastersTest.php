<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Models\Doctor;
use App\Models\Institution;
use App\Models\Patient;
use App\Models\SurgeryCase;
use App\Models\SurgeryType;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OperationalMastersTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_view_master_screens(): void
    {
        $user = $this->userWithRole('Jefe de Linea');

        $this->actingAs($user)
            ->get(route('masters.institutions.index'))
            ->assertOk()
            ->assertSee('Instituciones')
            ->assertSee('Maestros')
            ->assertSee('Medicos');

        $this->actingAs($user)
            ->get(route('masters.doctors.index'))
            ->assertOk()
            ->assertSee('Medicos');
    }

    public function test_user_without_master_permission_cannot_access_masters(): void
    {
        $user = $this->userWithRole('Instrumentista');

        $this->actingAs($user)->get(route('masters.institutions.index'))->assertForbidden();
        $this->actingAs($user)->get(route('masters.doctors.index'))->assertForbidden();
    }

    public function test_authorized_user_can_create_institution_and_duplicate_ruc_fails(): void
    {
        $user = $this->userWithRole('Comercial');
        $payload = $this->institutionPayload('Clinica Maestra QA', '20123456789');

        $response = $this->actingAs($user)->post(route('masters.institutions.store'), $payload);
        $institution = Institution::query()->where('ruc', '20123456789')->firstOrFail();

        $response->assertRedirect(route('masters.institutions.show', $institution));
        $this->assertDatabaseHas('audit_logs', ['action' => 'institution.created', 'auditable_id' => $institution->id]);

        $this->actingAs($user)
            ->post(route('masters.institutions.store'), $this->institutionPayload('Otra institucion', '20123456789'))
            ->assertSessionHasErrors('ruc');
    }

    public function test_authorized_user_can_create_doctor_and_duplicate_cmp_fails(): void
    {
        $user = $this->userWithRole('Jefe de Linea');
        $payload = $this->doctorPayload('Dr. Maestro QA', 'CMP-1001');

        $response = $this->actingAs($user)->post(route('masters.doctors.store'), $payload);
        $doctor = Doctor::query()->where('cmp', 'CMP-1001')->firstOrFail();

        $response->assertRedirect(route('masters.doctors.show', $doctor));
        $this->assertDatabaseHas('audit_logs', ['action' => 'doctor.created', 'auditable_id' => $doctor->id]);

        $this->actingAs($user)
            ->patch(route('masters.doctors.update', $doctor), array_merge($payload, ['active' => 0]))
            ->assertRedirect(route('masters.doctors.show', $doctor));
        $this->assertDatabaseHas('audit_logs', ['action' => 'doctor.disabled', 'auditable_id' => $doctor->id]);

        $this->actingAs($user)
            ->post(route('masters.doctors.store'), $this->doctorPayload('Otro medico', 'CMP-1001'))
            ->assertSessionHasErrors('cmp');
    }

    public function test_inactive_masters_are_not_available_for_new_cases(): void
    {
        $user = $this->userWithRole('Administrador');
        $activeInstitution = Institution::create(['name' => 'Institucion activa QA', 'active' => true]);
        $inactiveInstitution = Institution::create(['name' => 'Institucion inactiva QA', 'active' => false]);
        $activeDoctor = Doctor::create(['name' => 'Medico activo QA', 'active' => true]);
        $inactiveDoctor = Doctor::create(['name' => 'Medico inactivo QA', 'active' => false]);
        $type = SurgeryType::create(['code' => 'qa_master', 'name' => 'Cirugia QA maestros', 'active' => true]);
        $patient = Patient::create(['code' => 'PAT-MASTER-QA', 'full_name' => 'Paciente maestros QA']);
        $case = SurgeryCase::create([
            'case_code' => 'MR8-MASTER-QA',
            'status' => CaseStatus::SolicitudRegistrada,
            'institution_id' => $activeInstitution->id,
            'doctor_id' => $activeDoctor->id,
            'patient_id' => $patient->id,
            'surgery_type_id' => $type->id,
            'scheduled_at' => now()->addDay(),
            'priority' => 'normal',
            'procedure_name' => 'Material QA',
            'request_origin' => 'correo',
            'notes' => 'Caso QA.',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->get(route('cases.create'))
            ->assertOk()
            ->assertDontSee('value="'.$inactiveInstitution->id.'"')
            ->assertDontSee('value="'.$inactiveDoctor->id.'"');

        $this->actingAs($user)->get(route('cases.edit', $case))
            ->assertOk()
            ->assertDontSee('value="'.$inactiveInstitution->id.'"')
            ->assertDontSee('value="'.$inactiveDoctor->id.'"');
    }

    public function test_case_uses_institutions_and_doctors_created_from_masters(): void
    {
        $user = $this->userWithRole('Administrador');
        $institutionResponse = $this->actingAs($user)->post(route('masters.institutions.store'), $this->institutionPayload('Institucion integrada QA', '20123456788'));
        $institution = Institution::query()->where('ruc', '20123456788')->firstOrFail();
        $doctorResponse = $this->actingAs($user)->post(route('masters.doctors.store'), $this->doctorPayload('Dr. Integrado QA', 'CMP-1002') + ['institution_id' => $institution->id]);
        $doctor = Doctor::query()->where('cmp', 'CMP-1002')->firstOrFail();
        $type = SurgeryType::create(['code' => 'qa_integrated', 'name' => 'Cirugia integrada QA', 'active' => true]);

        $response = $this->actingAs($user)->post(route('cases.store'), [
            'institution_id' => $institution->id,
            'doctor_id' => $doctor->id,
            'patient_name' => 'Paciente integrado QA',
            'surgery_type_id' => $type->id,
            'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'priority' => 'normal',
            'material_requested' => 'Kit integrado QA',
            'request_origin' => 'llamada',
            'notes' => 'Solicitud creada desde maestros.',
        ]);

        $case = SurgeryCase::query()->where('institution_id', $institution->id)->latest('id')->firstOrFail();

        $institutionResponse->assertRedirect();
        $doctorResponse->assertRedirect();
        $response->assertRedirect(route('cases.show', $case));
        $this->assertSame($doctor->id, $case->doctor_id);
    }

    public function test_disabling_a_master_is_audited_and_future_active_institution_is_protected(): void
    {
        $user = $this->userWithRole('Administrador');
        $institution = Institution::create(['name' => 'Institucion estado QA', 'active' => true]);

        $this->actingAs($user)->patch(route('masters.institutions.update', $institution), array_merge($this->institutionPayload($institution->name, null), ['active' => 0]));

        $this->assertFalse($institution->refresh()->active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'institution.disabled', 'auditable_id' => $institution->id]);

        $protected = Institution::create(['name' => 'Institucion protegida QA', 'active' => true]);
        $doctor = Doctor::create(['name' => 'Medico protegido QA', 'active' => true]);
        $type = SurgeryType::create(['code' => 'qa_protected', 'name' => 'Cirugia protegida QA', 'active' => true]);
        $case = SurgeryCase::create([
            'case_code' => 'MR8-PROTECTED-QA',
            'status' => CaseStatus::Programada,
            'institution_id' => $protected->id,
            'doctor_id' => $doctor->id,
            'surgery_type_id' => $type->id,
            'scheduled_at' => now()->addDays(3),
            'priority' => 'normal',
            'procedure_name' => 'Material protegido',
            'request_origin' => 'correo',
            'notes' => 'Caso futuro activo.',
        ]);

        $this->actingAs($user)
            ->patch(route('masters.institutions.update', $protected), array_merge($this->institutionPayload($protected->name, null), ['active' => 0]))
            ->assertSessionHasErrors('institution');

        $this->assertTrue($protected->refresh()->active);
        $this->assertNotNull($case->id);
    }

    /** @return array<string, mixed> */
    private function institutionPayload(string $name, ?string $ruc): array
    {
        return [
            'name' => $name,
            'ruc' => $ruc,
            'institution_type' => 'clinica_privada',
            'billing_policy' => 'regular',
            'debt_status' => 'al_dia',
            'active' => 1,
        ];
    }

    /** @return array<string, mixed> */
    private function doctorPayload(string $name, ?string $cmp): array
    {
        return [
            'name' => $name,
            'cmp' => $cmp,
            'specialty' => 'neurocirugia',
            'commercial_profile' => 'nuevo',
            'potential' => 'medio',
            'active' => 1,
        ];
    }

    private function userWithRole(string $roleName): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create(['active' => true]);
        $user->assignRole(Role::findByName($roleName));

        return $user;
    }
}
