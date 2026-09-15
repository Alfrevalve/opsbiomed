<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\BillingRecord;
use App\Models\CaseMaterialUsed;
use App\Models\CaseReturn;
use App\Models\CaseValuation;
use App\Models\Doctor;
use App\Models\DocumentEvidence;
use App\Models\Failure;
use App\Models\Institution;
use App\Models\InventoryLot;
use App\Models\Patient;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\SurgeryCase;
use App\Models\SurgeryType;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SurgeryCaseControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_view_operational_control_with_case_data_alerts_and_documents(): void
    {
        $administrator = $this->administrator();
        $case = $this->operationalCase($administrator);

        $this->actingAs($administrator)
            ->get(route('cases.control', $case))
            ->assertOk()
            ->assertSee('Control operativo por cirugia')
            ->assertSee($case->case_code)
            ->assertSee('Clinica de control')
            ->assertSee('Material quirurgico')
            ->assertSee('MR8-CONTROL-01')
            ->assertSee('Documento de control')
            ->assertSee('Alertas operativas')
            ->assertSee('Documento pendiente de validar')
            ->assertSee('Falla abierta')
            ->assertSee('Devolucion pendiente de inspeccion')
            ->assertSee('Diferencia sin conciliar')
            ->assertSee('Facturacion y cobranza')
            ->assertSee('S/ 125.00')
            ->assertSee('case.updated');
    }

    public function test_user_without_case_view_permission_cannot_access_operational_control(): void
    {
        $case = $this->operationalCase(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->get(route('cases.control', $case))
            ->assertForbidden();
    }

    public function test_financial_information_is_hidden_without_billing_view_permission(): void
    {
        $user = $this->userWithPermissions(['cases.view']);
        $case = $this->operationalCase($user);

        $this->actingAs($user)
            ->get(route('cases.control', $case))
            ->assertOk()
            ->assertDontSee('Facturacion y cobranza')
            ->assertDontSee('S/ 125.00');
    }

    public function test_control_links_are_available_from_case_detail_listing_and_agenda(): void
    {
        $administrator = $this->administrator();
        $case = $this->operationalCase($administrator);
        $controlUrl = route('cases.control', $case);

        $this->actingAs($administrator)
            ->get(route('cases.show', $case))
            ->assertOk()
            ->assertSee($controlUrl, false);
        $this->actingAs($administrator)
            ->get(route('cases.index'))
            ->assertOk()
            ->assertSee($controlUrl, false);
        $this->actingAs($administrator)
            ->get(route('dashboard.ops'))
            ->assertOk()
            ->assertSee($controlUrl, false);
    }

    private function administrator(): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $administrator = User::factory()->create();
        $administrator->assignRole(Role::findByName('Administrador'));

        return $administrator;
    }

    /** @param list<string> $permissions */
    private function userWithPermissions(array $permissions): User
    {
        $role = Role::findOrCreate('Control operativo lectura');
        $role->syncPermissions(
            collect($permissions)->map(fn (string $permission): Permission => Permission::findOrCreate($permission)),
        );
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function operationalCase(User $user): SurgeryCase
    {
        $institution = Institution::create([
            'name' => 'Clinica de control '.uniqid(),
            'active' => true,
        ]);
        $doctor = Doctor::create([
            'name' => 'Dr. Control '.uniqid(),
            'institution_id' => $institution->id,
            'active' => true,
        ]);
        $patient = Patient::create([
            'code' => 'CTRL-'.uniqid(),
            'full_name' => 'Paciente de control',
        ]);
        $surgeryType = SurgeryType::create([
            'code' => 'CTRL-'.uniqid(),
            'name' => 'Cirugia de control',
            'active' => true,
        ]);
        $case = SurgeryCase::create([
            'case_code' => 'MR8-CONTROL-'.uniqid(),
            'status' => CaseStatus::Cerrado,
            'institution_id' => $institution->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'surgery_type_id' => $surgeryType->id,
            'scheduled_at' => now()->addHours(2),
            'priority' => 'normal',
            'procedure_name' => 'Material de control',
            'request_origin' => 'otro',
            'notes' => 'Caso de prueba para control operativo.',
            'created_by' => $user->id,
        ]);
        $product = Product::create([
            'product_code' => 'MR8-CONTROL-01',
            'name' => 'Fresa de control',
            'classification' => 'consumible',
            'expiry_required' => true,
            'active' => true,
        ]);
        $warehouse = Warehouse::create([
            'name' => 'Principal control '.uniqid(),
            'type' => 'principal',
            'lead_time_hours' => 0,
            'counts_as_immediate' => true,
            'active' => true,
        ]);
        $lot = InventoryLot::create([
            'product_id' => $product->id,
            'lot' => 'LOT-CONTROL-'.uniqid(),
            'serial' => 'SER-CONTROL-'.uniqid(),
            'expiry' => now()->addYear(),
            'warehouse_id' => $warehouse->id,
            'quantity' => 4,
            'status' => 'cuarentena',
            'eligible_flag' => false,
        ]);
        $reservation = Reservation::create([
            'case_id' => $case->id,
            'inventory_lot_id' => $lot->id,
            'quantity' => 2,
            'status' => 'partially_consumed',
            'reserved_by' => $user->id,
        ]);
        CaseMaterialUsed::create([
            'case_id' => $case->id,
            'reservation_id' => $reservation->id,
            'inventory_lot_id' => $lot->id,
            'reserved_qty' => 2,
            'used_qty' => 1,
            'unused_opened_qty' => 0,
            'returned_qty' => 0,
            'failure_qty' => 0,
            'difference_qty' => 1,
            'difference_reason' => 'Pendiente de investigacion',
            'unit_price' => 125,
            'subtotal' => 125,
            'reported_by' => $user->id,
        ]);
        CaseReturn::create([
            'case_id' => $case->id,
            'inventory_lot_id' => $lot->id,
            'returned_qty' => 1,
            'condition' => 'pendiente_inspeccion',
        ]);
        Failure::create([
            'case_id' => $case->id,
            'inventory_lot_id' => $lot->id,
            'product_id' => $product->id,
            'failure_type' => 'falla_mecanica',
            'occurrence_moment' => 'durante_cirugia',
            'severity' => 'alta',
            'status' => 'reportada',
            'preventive_block' => true,
            'description' => 'Falla de control.',
            'reported_by' => $user->id,
        ]);
        $valuation = CaseValuation::create([
            'case_id' => $case->id,
            'subtotal' => 125,
            'total' => 125,
            'currency' => 'PEN',
            'status' => 'costo_cero_pendiente_aprobacion',
            'created_by' => $user->id,
        ]);
        Approval::create([
            'case_id' => $case->id,
            'valuation_id' => $valuation->id,
            'type' => 'cost_zero',
            'status' => 'pendiente_aprobacion',
            'requested_by' => $user->id,
            'reason' => 'Control de costo cero.',
        ]);
        BillingRecord::create([
            'case_id' => $case->id,
            'amount' => 125,
            'amount_paid' => 0,
            'currency' => 'PEN',
            'invoice_status' => 'pendiente_factura',
            'payment_status' => 'vencido',
            'due_date' => now()->subDay()->toDateString(),
        ]);
        DocumentEvidence::create([
            'documentable_type' => SurgeryCase::class,
            'documentable_id' => $case->id,
            'document_type' => 'solicitud',
            'title' => 'Documento de control',
            'link_url' => 'https://example.test/control',
            'uploaded_by' => $user->id,
            'status' => 'cargado',
        ]);
        AuditLog::create([
            'user_id' => $user->id,
            'auditable_type' => SurgeryCase::class,
            'auditable_id' => $case->id,
            'action' => 'case.updated',
            'before' => ['notes' => 'Antes'],
            'after' => ['notes' => 'Despues'],
            'created_at' => now(),
        ]);

        return $case;
    }
}
