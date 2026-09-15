<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Enums\InventoryStatus;
use App\Models\CaseReturn;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReturnInspectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_return_appears_in_returns_list(): void
    {
        [$user, $case, $lot, $return] = $this->fixture(['returns.view', 'returns.inspect']);

        $this->actingAs($user)
            ->get(route('returns.index'))
            ->assertOk()
            ->assertSee($case->case_code)
            ->assertSee($lot->product->product_code)
            ->assertSee('Pendiente de inspeccion');
    }

    public function test_apt_return_releases_stock_when_lot_is_safe(): void
    {
        [$user, , $lot, $return] = $this->fixture(
            ['returns.view', 'returns.inspect', 'returns.release'],
            'Direccion Tecnica',
        );

        $this->actingAs($user)
            ->post(route('returns.inspect', $return), $this->inspectionData($user, 'apto_para_retorno'))
            ->assertRedirect(route('returns.show', $return));

        $this->assertDatabaseHas('case_returns', [
            'id' => $return->id,
            'condition' => 'liberado',
            'inspection_result' => 'apto_para_retorno',
        ]);
        $this->assertSame(InventoryStatus::Apto, $lot->refresh()->status);
        $this->assertTrue($lot->eligible_flag);
        $this->assertDatabaseHas('audit_logs', ['action' => 'return.inspected', 'auditable_id' => $return->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'inventory.released', 'auditable_id' => $lot->id]);
    }

    public function test_failure_in_return_creates_failure_and_blocks_lot(): void
    {
        [$user, $case, $lot, $return] = $this->fixture(
            ['returns.view', 'returns.inspect', 'returns.release'],
            'Direccion Tecnica',
        );

        $this->actingAs($user)
            ->post(route('returns.inspect', $return), $this->inspectionData($user, 'falla_detectada'))
            ->assertRedirect(route('returns.show', $return));

        $failure = Failure::query()->where('inventory_lot_id', $lot->id)->latest('id')->firstOrFail();
        $this->assertDatabaseHas('case_returns', [
            'id' => $return->id,
            'condition' => 'bloqueado',
            'inspection_result' => 'falla_detectada',
            'technical_failure_id' => $failure->id,
        ]);
        $this->assertDatabaseHas('failures', [
            'id' => $failure->id,
            'case_id' => $case->id,
            'status' => 'bloqueada',
            'preventive_block' => true,
        ]);
        $this->assertSame(InventoryStatus::FallaPreventiva, $lot->refresh()->status);
        $this->assertFalse($lot->eligible_flag);
        $this->assertDatabaseHas('audit_logs', ['action' => 'failure.reported', 'auditable_id' => $failure->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'inventory.blocked', 'auditable_id' => $lot->id]);
    }

    public function test_cleaning_result_leaves_return_and_lot_in_quarantine(): void
    {
        [$user, , $lot, $return] = $this->fixture(['returns.view', 'returns.inspect'], 'Almacen');

        $this->actingAs($user)
            ->post(route('returns.inspect', $return), $this->inspectionData($user, 'requiere_limpieza'))
            ->assertRedirect(route('returns.show', $return));

        $this->assertDatabaseHas('case_returns', ['id' => $return->id, 'condition' => 'cuarentena']);
        $this->assertSame(InventoryStatus::Cuarentena, $lot->refresh()->status);
        $this->assertFalse($lot->eligible_flag);
        $this->assertDatabaseHas('audit_logs', ['action' => 'inventory.quarantined', 'auditable_id' => $lot->id]);
    }

    public function test_expired_lot_cannot_be_released_from_return_inspection(): void
    {
        [$user, , $lot, $return] = $this->fixture(
            ['returns.view', 'returns.inspect', 'returns.release'],
            'Direccion Tecnica',
            ['expiry' => now()->subDay()],
        );

        $this->actingAs($user)
            ->post(route('returns.inspect', $return), $this->inspectionData($user, 'apto_para_retorno'))
            ->assertRedirect()
            ->assertSessionHasErrors('inspection');

        $this->assertSame('pendiente_inspeccion', $return->refresh()->condition);
        $this->assertSame(InventoryStatus::Cuarentena, $lot->refresh()->status);
    }

    public function test_user_without_inspect_permission_cannot_inspect_return(): void
    {
        [$user, , , $return] = $this->fixture(['returns.view']);

        $this->actingAs($user)
            ->get(route('returns.inspect.create', $return))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('returns.inspect', $return), $this->inspectionData($user))
            ->assertForbidden();
    }

    public function test_dashboard_counts_pending_return_inspections(): void
    {
        [$user, $case] = $this->fixture(['dashboard.view', 'cases.view', 'returns.view']);

        $this->actingAs($user)
            ->get(route('dashboard.ops'))
            ->assertOk()
            ->assertSee('Devoluciones pendientes de inspeccion')
            ->assertSee('Calidad tecnica');
    }

    public function test_non_reusable_return_is_removed_from_eligible_stock(): void
    {
        [$user, , $lot, $return] = $this->fixture(
            ['returns.view', 'returns.inspect', 'returns.release'],
            'Direccion Tecnica',
        );

        $this->actingAs($user)
            ->post(route('returns.inspect', $return), $this->inspectionData($user, 'no_reutilizable'))
            ->assertRedirect(route('returns.show', $return));

        $this->assertDatabaseHas('case_returns', ['id' => $return->id, 'condition' => 'desvalorizado']);
        $this->assertSame(InventoryStatus::Desvalorizado, $lot->refresh()->status);
        $this->assertFalse($lot->eligible_flag);
    }

    /**
     * @param  list<string>  $permissions
     * @param  array<string, mixed>  $lotOverrides
     * @return array{0: User, 1: SurgeryCase, 2: InventoryLot, 3: CaseReturn}
     */
    private function fixture(array $permissions, string $roleName = 'return-inspector', array $lotOverrides = []): array
    {
        $role = Role::findOrCreate($roleName);
        $role->syncPermissions(collect($permissions)->map(fn (string $permission) => Permission::findOrCreate($permission)));
        $user = User::factory()->create();
        $user->assignRole($role);

        $institution = Institution::create(['name' => 'Institucion retorno '.uniqid(), 'active' => true]);
        $doctor = Doctor::create([
            'name' => 'Medico retorno '.uniqid(),
            'specialty' => 'Neurocirugia',
            'institution_id' => $institution->id,
            'active' => true,
        ]);
        $type = SurgeryType::create(['code' => 'retorno_'.uniqid(), 'name' => 'Cirugia retorno '.uniqid(), 'active' => true]);
        $patient = Patient::create(['code' => 'PAT-'.uniqid(), 'full_name' => 'Paciente retorno '.uniqid()]);
        $case = SurgeryCase::create([
            'case_code' => 'MR8-RETURN-'.uniqid(),
            'status' => CaseStatus::Cerrado,
            'institution_id' => $institution->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'surgery_type_id' => $type->id,
            'scheduled_at' => now()->subDay(),
            'priority' => 'normal',
            'procedure_name' => 'Retorno de material MR8',
            'request_origin' => 'whatsapp',
            'notes' => 'Caso de prueba de devolucion.',
            'created_by' => $user->id,
        ]);
        $warehouse = Warehouse::create([
            'name' => 'Principal retorno '.uniqid(),
            'type' => 'principal',
            'lead_time_hours' => 0,
            'counts_as_immediate' => true,
            'active' => true,
        ]);
        $product = Product::create([
            'product_line' => 'MR8',
            'family' => 'Fresas',
            'subfamily' => 'Retorno',
            'product_code' => 'MR8-RETURN-'.uniqid(),
            'name' => 'Producto retorno',
            'classification' => 'consumible',
            'expiry_required' => false,
            'tracking_type' => 'lot',
            'active' => true,
        ]);
        $lot = InventoryLot::create(array_merge([
            'product_id' => $product->id,
            'lot' => 'RETURN-LOT-'.uniqid(),
            'serial' => null,
            'expiry' => now()->addYear(),
            'warehouse_id' => $warehouse->id,
            'quantity' => 5,
            'status' => InventoryStatus::Cuarentena,
            'eligible_flag' => false,
            'block_reason' => 'Material pendiente de inspeccion.',
        ], $lotOverrides));
        $return = CaseReturn::create([
            'case_id' => $case->id,
            'inventory_lot_id' => $lot->id,
            'returned_qty' => 2,
            'condition' => 'pendiente_inspeccion',
        ]);

        return [$user, $case, $lot->load(['product', 'warehouse']), $return];
    }

    /** @return array<string, mixed> */
    private function inspectionData(User $user, string $result = 'requiere_revision_tecnica'): array
    {
        return [
            'inspection_result' => $result,
            'inspection_observations' => 'Inspeccion fisica registrada para prueba.',
            'inspection_evidence_reference' => 'ACTA-RETURN-001',
            'inspection_responsible_id' => $user->id,
            'inspection_date' => now()->format('Y-m-d H:i:s'),
        ];
    }
}
