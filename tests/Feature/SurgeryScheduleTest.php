<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Models\AuditLog;
use App\Models\CaseResourceAssignment;
use App\Models\Doctor;
use App\Models\Institution;
use App\Models\InventoryLot;
use App\Models\KitRule;
use App\Models\Patient;
use App\Models\Product;
use App\Models\SurgeryCase;
use App\Models\SurgeryType;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SurgeryScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_schedule_view_can_view_the_agenda(): void
    {
        $user = $this->userWithPermissions(['schedule.view']);

        $this->actingAs($user)
            ->get(route('schedule.index'))
            ->assertOk()
            ->assertSee('Agenda y disponibilidad de recursos');
    }

    public function test_user_without_schedule_view_cannot_access_the_agenda(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('schedule.index'))
            ->assertForbidden();
    }

    public function test_case_appears_in_the_agenda(): void
    {
        $user = $this->userWithPermissions(['schedule.view']);
        $case = $this->surgeryCase($user, now()->addHours(4));

        $this->actingAs($user)
            ->get(route('schedule.index'))
            ->assertOk()
            ->assertSee($case->case_code)
            ->assertSee('Clinica agenda');
    }

    public function test_conflict_for_the_same_instrumentist_is_detected(): void
    {
        $user = $this->userWithPermissions(['schedule.view']);
        $instrumentist = $this->instrumentist();
        $scheduledAt = now()->addHours(4)->startOfMinute();
        $firstCase = $this->surgeryCase($user, $scheduledAt);
        $secondCase = $this->surgeryCase($user, $scheduledAt);
        $firstCase->update(['assigned_instrumentist_id' => $instrumentist->id]);
        $secondCase->update(['assigned_instrumentist_id' => $instrumentist->id]);

        $this->actingAs($user)
            ->get(route('schedule.conflicts'))
            ->assertOk()
            ->assertSee('Cruce Instrumentista')
            ->assertSee($instrumentist->name)
            ->assertSee($firstCase->case_code)
            ->assertSee($secondCase->case_code);
    }

    public function test_conflict_for_the_same_reusable_resource_is_detected(): void
    {
        $user = $this->userWithPermissions(['schedule.view']);
        $scheduledAt = now()->addHours(4)->startOfMinute();
        $firstCase = $this->surgeryCase($user, $scheduledAt);
        $secondCase = $this->surgeryCase($user, $scheduledAt);
        $lot = $this->reusableResourceLot();

        CaseResourceAssignment::create([
            'surgery_case_id' => $firstCase->id,
            'inventory_lot_id' => $lot->id,
            'resource_type' => 'motor',
            'assigned_by' => $user->id,
            'assigned_at' => now(),
        ]);
        CaseResourceAssignment::create([
            'surgery_case_id' => $secondCase->id,
            'inventory_lot_id' => $lot->id,
            'resource_type' => 'motor',
            'assigned_by' => $user->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('schedule.conflicts'))
            ->assertOk()
            ->assertSee('Cruce Recurso')
            ->assertSee($lot->product->product_code);
    }

    public function test_case_without_complete_reservation_appears_as_an_alert(): void
    {
        $user = $this->userWithPermissions(['schedule.view']);
        $case = $this->surgeryCase($user, now()->addHours(4));
        KitRule::create([
            'surgery_type_id' => $case->surgery_type_id,
            'length_cm' => 9,
            'diameter_mm' => 2,
            'cut_type' => 'cortante',
            'component_type' => 'fresa',
            'min_qty' => 1,
            'target_qty' => 5,
            'required' => true,
        ]);

        $this->actingAs($user)
            ->get(route('schedule.conflicts'))
            ->assertOk()
            ->assertSee('Reserva Incompleta')
            ->assertSee($case->case_code);
    }

    public function test_schedule_assignments_are_audited(): void
    {
        $manager = $this->userWithPermissions(['schedule.view', 'schedule.manage']);
        $instrumentist = $this->instrumentist();
        $case = $this->surgeryCase($manager, now()->addHours(4));
        $lot = $this->reusableResourceLot();

        $this->actingAs($manager)
            ->patch(route('cases.schedule.instrumentist.update', $case), [
                'assigned_instrumentist_id' => $instrumentist->id,
            ])
            ->assertRedirect(route('cases.control', $case));
        $this->actingAs($manager)
            ->post(route('cases.resources.store', $case), [
                'resource_type' => 'motor',
                'inventory_lot_id' => $lot->id,
            ])
            ->assertRedirect(route('cases.control', $case));

        $this->assertDatabaseHas('surgery_cases', [
            'id' => $case->id,
            'assigned_instrumentist_id' => $instrumentist->id,
        ]);
        $this->assertDatabaseHas('case_resource_assignments', [
            'surgery_case_id' => $case->id,
            'inventory_lot_id' => $lot->id,
            'resource_type' => 'motor',
        ]);
        $this->assertTrue(AuditLog::query()
            ->where('action', 'schedule.instrumentist_assigned')
            ->exists());
        $this->assertTrue(AuditLog::query()
            ->where('action', 'schedule.resource_assigned')
            ->exists());
    }

    public function test_same_instrumentist_cannot_be_assigned_to_overlapping_cases(): void
    {
        $manager = $this->userWithPermissions(['schedule.view', 'schedule.manage']);
        $instrumentist = $this->instrumentist();
        $scheduledAt = now()->addHours(4)->startOfMinute();
        $firstCase = $this->surgeryCase($manager, $scheduledAt);
        $secondCase = $this->surgeryCase($manager, $scheduledAt->copy()->addSeconds(30));

        $this->actingAs($manager)
            ->patch(route('cases.schedule.instrumentist.update', $firstCase), [
                'assigned_instrumentist_id' => $instrumentist->id,
            ])
            ->assertRedirect(route('cases.control', $firstCase));
        $this->actingAs($manager)
            ->patch(route('cases.schedule.instrumentist.update', $secondCase), [
                'assigned_instrumentist_id' => $instrumentist->id,
            ])
            ->assertRedirect(route('cases.control', $secondCase))
            ->assertSessionHasErrors('assigned_instrumentist_id');

        $this->assertSame($instrumentist->id, $firstCase->refresh()->assigned_instrumentist_id);
        $this->assertNull($secondCase->refresh()->assigned_instrumentist_id);
        $this->assertSame(1, AuditLog::query()->where('action', 'schedule.instrumentist_assigned')->count());
    }

    public function test_same_reusable_resource_cannot_be_assigned_to_overlapping_cases(): void
    {
        $manager = $this->userWithPermissions(['schedule.view', 'schedule.manage']);
        $scheduledAt = now()->addHours(4)->startOfMinute();
        $firstCase = $this->surgeryCase($manager, $scheduledAt);
        $secondCase = $this->surgeryCase($manager, $scheduledAt->copy()->addSeconds(30));
        $lot = $this->reusableResourceLot();

        $this->actingAs($manager)
            ->post(route('cases.resources.store', $firstCase), [
                'resource_type' => 'motor',
                'inventory_lot_id' => $lot->id,
            ])
            ->assertRedirect(route('cases.control', $firstCase));
        $this->actingAs($manager)
            ->post(route('cases.resources.store', $secondCase), [
                'resource_type' => 'motor',
                'inventory_lot_id' => $lot->id,
            ])
            ->assertRedirect(route('cases.control', $secondCase))
            ->assertSessionHasErrors('resource_assignment');

        $this->assertSame(1, CaseResourceAssignment::query()->where('inventory_lot_id', $lot->id)->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'schedule.resource_assigned')->count());
    }

    public function test_dashboard_shows_the_schedule_conflict_counter(): void
    {
        $user = $this->userWithPermissions(['dashboard.view', 'cases.view', 'schedule.view']);
        $instrumentist = $this->instrumentist();
        $scheduledAt = now()->addHours(4)->startOfMinute();
        $firstCase = $this->surgeryCase($user, $scheduledAt);
        $secondCase = $this->surgeryCase($user, $scheduledAt);
        $firstCase->update(['assigned_instrumentist_id' => $instrumentist->id]);
        $secondCase->update(['assigned_instrumentist_id' => $instrumentist->id]);

        $this->actingAs($user)
            ->get(route('dashboard.ops'))
            ->assertOk()
            ->assertSee('Conflictos criticos')
            ->assertSee('Sin instrumentista')
            ->assertSee('Casos sin reserva completa');
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWithPermissions(array $permissions): User
    {
        $role = Role::findOrCreate('Agenda QA');
        $role->syncPermissions(
            collect($permissions)->map(
                fn (string $permission): Permission => Permission::findOrCreate($permission),
            ),
        );
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function instrumentist(): User
    {
        $instrumentist = User::factory()->create(['active' => true]);
        $instrumentist->assignRole(Role::findOrCreate('Instrumentista'));

        return $instrumentist;
    }

    private function surgeryCase(User $creator, Carbon $scheduledAt): SurgeryCase
    {
        $institution = Institution::create([
            'name' => 'Clinica agenda '.uniqid(),
            'debt_status' => 'al_dia',
            'active' => true,
        ]);
        $doctor = Doctor::create([
            'name' => 'Dr. Agenda '.uniqid(),
            'institution_id' => $institution->id,
            'active' => true,
        ]);
        $patient = Patient::create([
            'code' => 'AGENDA-'.uniqid(),
            'full_name' => 'Paciente Agenda',
        ]);
        $surgeryType = SurgeryType::create([
            'code' => 'AGENDA-'.uniqid(),
            'name' => 'Cirugia agenda '.uniqid(),
            'active' => true,
        ]);

        return SurgeryCase::create([
            'case_code' => 'MR8-AGENDA-'.uniqid(),
            'status' => CaseStatus::Programada,
            'institution_id' => $institution->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'surgery_type_id' => $surgeryType->id,
            'scheduled_at' => $scheduledAt,
            'priority' => 'normal',
            'procedure_name' => 'Material de agenda',
            'request_origin' => 'otro',
            'created_by' => $creator->id,
        ]);
    }

    private function reusableResourceLot(): InventoryLot
    {
        $product = Product::create([
            'product_code' => 'MR8-RECURSO-'.uniqid(),
            'name' => 'Motor reutilizable agenda',
            'classification' => 'reusable',
            'expiry_required' => false,
            'active' => true,
        ]);
        $warehouse = Warehouse::create([
            'name' => 'Principal agenda '.uniqid(),
            'type' => 'principal',
            'lead_time_hours' => 0,
            'counts_as_immediate' => true,
            'active' => true,
        ]);

        return InventoryLot::create([
            'product_id' => $product->id,
            'lot' => 'LOT-AGENDA-'.uniqid(),
            'serial' => 'SER-AGENDA-'.uniqid(),
            'warehouse_id' => $warehouse->id,
            'quantity' => 1,
            'status' => 'apto',
            'eligible_flag' => true,
        ])->load('product');
    }
}
