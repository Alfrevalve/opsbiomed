<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Enums\FailureStatus;
use App\Enums\InventoryStatus;
use App\Models\Doctor;
use App\Models\Failure;
use App\Models\Institution;
use App\Models\InventoryLot;
use App\Models\Patient;
use App\Models\Product;
use App\Models\SurgeryCase;
use App\Models\SurgeryType;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FailureManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_critical_failure_blocks_lot_and_is_audited(): void
    {
        $user = $this->userWithRole('Almacen', ['failures.view', 'failures.create']);
        $lot = $this->lot();

        $this->actingAs($user)->post(route('failures.store'), $this->failurePayload($lot, [
            'severity' => 'critica',
            'failure_type' => 'falla_mecanica',
        ]))->assertRedirect();

        $failure = Failure::query()->latest('id')->firstOrFail();
        $this->assertSame(FailureStatus::Bloqueada->value, $failure->status);
        $this->assertTrue((bool) $failure->preventive_block);
        $this->assertDatabaseHas('inventory_lots', [
            'id' => $lot->id,
            'status' => InventoryStatus::FallaPreventiva->value,
            'eligible_flag' => false,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'failure.reported', 'auditable_id' => $failure->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'inventory.blocked', 'auditable_id' => $lot->id]);
    }

    public function test_failure_during_surgery_blocks_lot_even_with_low_severity(): void
    {
        $user = $this->userWithRole('Instrumentista', ['failures.view', 'failures.create']);
        $lot = $this->lot();

        $this->actingAs($user)->post(route('failures.store'), $this->failurePayload($lot, [
            'severity' => 'baja',
            'occurrence_moment' => 'durante_cirugia',
        ]))->assertRedirect();

        $this->assertDatabaseHas('inventory_lots', [
            'id' => $lot->id,
            'status' => InventoryStatus::FallaPreventiva->value,
            'eligible_flag' => false,
        ]);
    }

    public function test_lot_blocked_by_failure_cannot_be_reserved(): void
    {
        $user = $this->userWithRole('Almacen', ['cases.view', 'reservations.create', 'dashboard.view', 'failures.create', 'failures.view']);
        [$lot, $case] = $this->lotAndCase($user);

        $this->actingAs($user)->post(route('failures.store'), $this->failurePayload($lot, [
            'severity' => 'alta',
        ]))->assertRedirect();

        $this->actingAs($user)
            ->post(route('cases.reserve', $case), ['inventory_lot_id' => $lot->id, 'quantity' => 1])
            ->assertRedirect(route('cases.reserve.create', $case))
            ->assertSessionHasErrors('quantity');
    }

    public function test_unauthorized_user_cannot_release_failure(): void
    {
        $creator = $this->userWithRole('Almacen', ['failures.view', 'failures.create']);
        $lot = $this->lot();
        $this->actingAs($creator)->post(route('failures.store'), $this->failurePayload($lot, ['severity' => 'alta']))->assertRedirect();
        $failure = Failure::query()->latest('id')->firstOrFail();

        $this->actingAs($creator)
            ->post(route('failures.release', $failure), $this->releasePayload())
            ->assertForbidden();
    }

    public function test_technical_direction_can_release_failure_with_diagnosis(): void
    {
        $user = $this->userWithRole('Direccion Tecnica', ['failures.view', 'failures.create', 'failures.release']);
        $lot = $this->lot();
        $this->actingAs($user)->post(route('failures.store'), $this->failurePayload($lot, ['severity' => 'alta']))->assertRedirect();
        $failure = Failure::query()->latest('id')->firstOrFail();

        $this->actingAs($user)
            ->post(route('failures.release', $failure), $this->releasePayload())
            ->assertRedirect(route('failures.show', $failure));

        $this->assertDatabaseHas('failures', ['id' => $failure->id, 'status' => FailureStatus::Liberada->value, 'preventive_block' => false]);
        $this->assertDatabaseHas('inventory_lots', ['id' => $lot->id, 'status' => InventoryStatus::Apto->value, 'eligible_flag' => true]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'failure.released', 'auditable_id' => $failure->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'inventory.released', 'auditable_id' => $lot->id]);
    }

    public function test_expired_lot_cannot_be_released(): void
    {
        $user = $this->userWithRole('Direccion Tecnica', ['failures.view', 'failures.create', 'failures.release']);
        $lot = $this->lot(['expiry' => now()->subDay(), 'status' => InventoryStatus::Vencido]);
        $this->actingAs($user)->post(route('failures.store'), $this->failurePayload($lot, ['severity' => 'alta']))->assertRedirect();
        $failure = Failure::query()->latest('id')->firstOrFail();

        $this->actingAs($user)
            ->post(route('failures.release', $failure), $this->releasePayload())
            ->assertRedirect()
            ->assertSessionHasErrors('failure');

        $this->assertDatabaseHas('failures', ['id' => $failure->id, 'status' => FailureStatus::Bloqueada->value]);
    }

    public function test_retiring_failure_devalues_the_lot(): void
    {
        $user = $this->userWithRole('Direccion Tecnica', ['failures.view', 'failures.create', 'failures.release']);
        $lot = $this->lot();
        $this->actingAs($user)->post(route('failures.store'), $this->failurePayload($lot, ['severity' => 'critica']))->assertRedirect();
        $failure = Failure::query()->latest('id')->firstOrFail();

        $this->actingAs($user)
            ->post(route('failures.retire', $failure), [
                'retirement_reason' => 'Dano no reparable.',
                'retirement_evidence' => 'Acta tecnica RT-001.',
            ])
            ->assertRedirect(route('failures.show', $failure));

        $this->assertDatabaseHas('failures', ['id' => $failure->id, 'status' => FailureStatus::DadaDeBaja->value]);
        $this->assertDatabaseHas('inventory_lots', ['id' => $lot->id, 'status' => InventoryStatus::Desvalorizado->value, 'eligible_flag' => false]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'failure.retired', 'auditable_id' => $failure->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'inventory.retired', 'auditable_id' => $lot->id]);
    }

    public function test_failure_index_detail_and_inventory_show_are_available(): void
    {
        $user = $this->userWithRole('Gerencia', ['failures.view', 'inventory.view']);
        $lot = $this->lot();
        $failure = Failure::create([
            'product_id' => $lot->product_id,
            'inventory_lot_id' => $lot->id,
            'failure_type' => 'desgaste',
            'occurrence_moment' => 'mantenimiento',
            'severity' => 'media',
            'status' => 'reportada',
            'description' => 'Desgaste detectado en inspeccion.',
            'reported_by' => $user->id,
        ]);

        $this->actingAs($user)->get(route('failures.index'))->assertOk()->assertSee('Mantenimiento y fallas tecnicas');
        $this->actingAs($user)->get(route('failures.show', $failure))->assertOk()->assertSee('Reporte de falla #'.$failure->id);
        $this->actingAs($user)->get(route('inventory.show', $lot))->assertOk()->assertSee('Fallas tecnicas asociadas')->assertSee('Ver reporte');
    }

    public function test_failure_create_and_edit_forms_are_available_to_authorized_users(): void
    {
        $user = $this->userWithRole('Jefe de Linea', ['failures.view', 'failures.create', 'failures.update']);
        $lot = $this->lot();
        $failure = Failure::create([
            'product_id' => $lot->product_id,
            'inventory_lot_id' => $lot->id,
            'failure_type' => 'desgaste',
            'occurrence_moment' => 'inventario',
            'severity' => 'media',
            'status' => 'reportada',
            'description' => 'Desgaste detectado en inspeccion.',
            'reported_by' => $user->id,
        ]);

        $this->actingAs($user)->get(route('failures.create'))->assertOk()->assertSee('Reportar falla tecnica');
        $this->actingAs($user)->get(route('failures.edit', $failure))->assertOk()->assertSee('Actualizar seguimiento de falla');
    }

    public function test_failure_status_filter_contains_only_unique_official_statuses(): void
    {
        $user = $this->userWithRole('Gerencia', ['failures.view']);

        $response = $this->actingAs($user)->get(route('failures.index'));
        $content = $response->getContent();

        $response->assertOk()->assertSee('Todos');
        foreach (FailureStatus::labels() as $value => $label) {
            $this->assertSame(1, substr_count($content, 'value="'.$value.'"'));
            $this->assertStringContainsString($label, $content);
        }

        $this->assertStringNotContainsString('value="abierta"', $content);
        $this->assertStringNotContainsString('value="resuelta"', $content);
        $this->assertStringNotContainsString('>Abierta<', $content);
        $this->assertStringNotContainsString('>Resuelta<', $content);
    }

    public function test_dashboard_shows_failure_metrics(): void
    {
        $user = $this->userWithRole('Gerencia', ['dashboard.view', 'cases.view', 'failures.view', 'inventory.view']);
        $lot = $this->lot();
        Failure::create([
            'product_id' => $lot->product_id,
            'inventory_lot_id' => $lot->id,
            'failure_type' => 'vibracion',
            'occurrence_moment' => 'inventario',
            'severity' => 'critica',
            'status' => 'reportada',
            'preventive_block' => true,
            'description' => 'Vibracion en prueba.',
            'reported_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard.ops'))
            ->assertOk()
            ->assertSee('Fallas abiertas')
            ->assertSee('Fallas criticas')
            ->assertSee('Calidad tecnica')
            ->assertSee('Fallas por tipo')
            ->assertSee('Vibracion');
    }

    public function test_failure_permissions_are_seeded_for_operational_roles(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $this->assertTrue(Role::findByName('Administrador')->hasPermissionTo('failures.close'));
        $this->assertTrue(Role::findByName('Direccion Tecnica')->hasPermissionTo('failures.release'));
        $this->assertTrue(Role::findByName('Almacen')->hasPermissionTo('failures.create'));
        $this->assertFalse(Role::findByName('Comercial')->hasPermissionTo('failures.release'));
    }

    /** @param list<string> $permissions */
    private function userWithRole(string $roleName, array $permissions): User
    {
        $role = Role::findOrCreate($roleName);
        $role->syncPermissions(collect($permissions)->map(fn (string $permission): Permission => Permission::findOrCreate($permission)));
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /** @param array<string, mixed> $overrides */
    private function lot(array $overrides = []): InventoryLot
    {
        $warehouse = Warehouse::create([
            'name' => 'Principal falla '.uniqid(),
            'type' => 'principal',
            'lead_time_hours' => 0,
            'counts_as_immediate' => true,
            'active' => true,
        ]);
        $product = Product::create([
            'product_code' => 'FAIL-'.uniqid(),
            'name' => 'Producto con trazabilidad',
            'classification' => 'consumible',
            'expiry_required' => true,
            'active' => true,
        ]);

        return InventoryLot::create(array_merge([
            'product_id' => $product->id,
            'lot' => 'LOT-'.uniqid(),
            'serial' => 'SER-'.uniqid(),
            'expiry' => now()->addYear(),
            'warehouse_id' => $warehouse->id,
            'quantity' => 10,
            'status' => InventoryStatus::Apto,
            'eligible_flag' => true,
        ], $overrides));
    }

    /** @return array{0: InventoryLot, 1: SurgeryCase} */
    private function lotAndCase(User $user): array
    {
        $lot = $this->lot();
        $institution = Institution::create(['name' => 'Institucion falla '.uniqid(), 'active' => true]);
        $doctor = Doctor::create(['name' => 'Medico falla '.uniqid(), 'institution_id' => $institution->id, 'active' => true]);
        $patient = Patient::create(['code' => 'PAT-'.uniqid(), 'full_name' => 'Paciente falla']);
        $type = SurgeryType::create(['code' => 'TYPE-'.uniqid(), 'name' => 'Cirugia falla '.uniqid(), 'active' => true]);
        $case = SurgeryCase::create([
            'case_code' => 'CASE-'.uniqid(),
            'status' => CaseStatus::SolicitudRegistrada,
            'institution_id' => $institution->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'surgery_type_id' => $type->id,
            'scheduled_at' => now()->addDay(),
            'priority' => 'normal',
            'procedure_name' => 'Prueba de falla',
            'request_origin' => 'whatsapp',
            'notes' => 'Caso de prueba.',
            'created_by' => $user->id,
        ]);

        return [$lot, $case];
    }

    /** @param array<string, mixed> $overrides */
    private function failurePayload(InventoryLot $lot, array $overrides = []): array
    {
        return array_merge([
            'product_id' => $lot->product_id,
            'inventory_lot_id' => $lot->id,
            'failure_type' => 'otro',
            'occurrence_moment' => 'inventario',
            'severity' => 'media',
            'description' => 'Falla registrada durante una inspeccion.',
            'action_taken' => 'Lote separado preventivamente.',
            'evidence_reference' => 'EVID-001',
        ], $overrides);
    }

    /** @return array<string, string> */
    private function releasePayload(): array
    {
        return [
            'diagnosis' => 'Falla corregida en banco de pruebas.',
            'corrective_action' => 'Se reemplazo el componente afectado.',
            'release_notes' => 'Prueba funcional aprobada. Evidencia TEC-001.',
        ];
    }
}
