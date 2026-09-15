<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BillingRecord;
use App\Models\CaseReturn;
use App\Models\Doctor;
use App\Models\DocumentEvidence;
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
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HardeningSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_load_principal_screens_with_empty_data(): void
    {
        $administrator = $this->administrator();

        $this->actingAs($administrator)->get(route('dashboard.ops'))->assertOk();
        $this->actingAs($administrator)->get(route('cases.index'))->assertOk()->assertSee('No hay solicitudes registradas.');
        $this->actingAs($administrator)->get(route('inventory.index'))->assertOk()->assertSee('No hay inventario registrado.');
        $this->actingAs($administrator)->get(route('inventory.coverage'))->assertOk();
        $this->actingAs($administrator)->get(route('inventory.forecast'))->assertOk();
        $this->actingAs($administrator)->get(route('catalog.imports.index'))->assertOk();
        $this->actingAs($administrator)->get(route('failures.index'))->assertOk()->assertSee('No hay fallas reportadas.');
        $this->actingAs($administrator)->get(route('returns.index'))->assertOk()->assertSee('No hay devoluciones pendientes.');
        $this->actingAs($administrator)->get(route('billing.index'))->assertOk()->assertSee('No hay facturas pendientes.');
        $this->actingAs($administrator)->get(route('approvals.cost-zero.index'))->assertOk();
        $this->actingAs($administrator)->get(route('reports.index'))->assertOk();
        $this->actingAs($administrator)->get(route('reports.operations'))->assertOk();
        $this->actingAs($administrator)->get(route('reports.commercial'))->assertOk();
        $this->actingAs($administrator)->get(route('reports.billing'))->assertOk();
        $this->actingAs($administrator)->get(route('reports.inventory'))->assertOk();
        $this->actingAs($administrator)->get(route('documents.index'))->assertOk()->assertSee('No hay documentos cargados.');

        $dashboard = $this->actingAs($administrator)->get(route('dashboard.ops'));
        $dashboard->assertSee('ops-page', false);
        $dashboard->assertSee('ops-sidebar', false);
        $dashboard->assertSee('ops-card', false);
        $dashboard->assertSee('ops-button-primary', false);
        $dashboard->assertSee('Torre de Control Quirurgica');
        $dashboard->assertSee('Linea Midas Rex MR8');
        $dashboard->assertSee('Prioridades de la operacion');
        $dashboard->assertSee('Agenda quirurgica');
        $dashboard->assertSee('Finanzas y cobranza');
        $dashboard->assertSee('Calidad tecnica');
        $dashboard->assertSee('Actividad comercial');
        $dashboard->assertSee('Solicitudes');
        $dashboard->assertSee('Inventario');
        $dashboard->assertSee('Cobertura MR8');
        $dashboard->assertSee('Forecast MR8');
        $dashboard->assertSee('Catalogo / Importar Excel');
        $dashboard->assertSee('Fallas tecnicas');
        $dashboard->assertSee('Devoluciones');
        $dashboard->assertSee('Facturacion / Cobranza');
        $dashboard->assertSee('Aprobaciones');
        $dashboard->assertSee('Reportes');
        $dashboard->assertSee('Documentos');
        $dashboard->assertSeeInOrder([
            'Dashboard',
            'Solicitudes',
            'Devoluciones',
            'Fallas tecnicas',
            'Documentos',
            'Inventario',
            'Cobertura MR8',
            'Forecast MR8',
            'Catalogo / Importar Excel',
            'Facturacion / Cobranza',
            'Aprobaciones',
            'Reportes',
            'Instituciones',
            'Medicos',
            'Precios',
            'Usuarios',
        ]);
    }

    public function test_principal_detail_screens_load_with_data_and_audit_logs(): void
    {
        $administrator = $this->administrator();
        [$case, $lot, $failure, $return] = $this->operationalData($administrator);

        AuditLog::create([
            'user_id' => $administrator->id,
            'auditable_type' => InventoryLot::class,
            'auditable_id' => $lot->id,
            'action' => 'inventory.updated',
            'before' => ['quantity' => 10],
            'after' => ['quantity' => 9],
            'created_at' => now(),
        ]);
        AuditLog::create([
            'user_id' => $administrator->id,
            'auditable_type' => Failure::class,
            'auditable_id' => $failure->id,
            'action' => 'failure.reported',
            'before' => [],
            'after' => ['status' => 'reportada'],
            'created_at' => now(),
        ]);
        AuditLog::create([
            'user_id' => $administrator->id,
            'auditable_type' => CaseReturn::class,
            'auditable_id' => $return->id,
            'action' => 'return.created',
            'before' => [],
            'after' => ['condition' => 'pendiente_inspeccion'],
            'created_at' => now(),
        ]);
        AuditLog::create([
            'user_id' => $administrator->id,
            'auditable_type' => SurgeryCase::class,
            'auditable_id' => $case->id,
            'action' => 'case.updated',
            'before' => ['notes' => 'Antes'],
            'after' => ['notes' => 'Despues'],
            'created_at' => now(),
        ]);
        $billing = BillingRecord::create([
            'case_id' => $case->id,
            'amount' => 100,
            'amount_paid' => 0,
            'invoice_status' => 'pendiente_factura',
            'payment_status' => 'pendiente',
        ]);
        AuditLog::create([
            'user_id' => $administrator->id,
            'auditable_type' => BillingRecord::class,
            'auditable_id' => $billing->id,
            'action' => 'billing.updated',
            'before' => ['payment_status' => 'pendiente'],
            'after' => ['payment_status' => 'parcial'],
            'created_at' => now(),
        ]);
        $document = $this->document($case, $administrator);
        AuditLog::create([
            'user_id' => $administrator->id,
            'auditable_type' => DocumentEvidence::class,
            'auditable_id' => $document->id,
            'action' => 'document.validated',
            'before' => ['status' => 'cargado'],
            'after' => ['status' => 'validado'],
            'created_at' => now(),
        ]);

        $this->actingAs($administrator)->get(route('cases.show', $case))->assertOk()->assertSee('case.updated');
        $this->actingAs($administrator)->get(route('inventory.show', $lot))->assertOk()->assertSee('inventory.updated');
        $this->actingAs($administrator)->get(route('failures.show', $failure))->assertOk()->assertSee('failure.reported');
        $this->actingAs($administrator)->get(route('returns.show', $return))->assertOk()->assertSee('return.created');
        $this->actingAs($administrator)->get(route('billing.show', $case))->assertOk()->assertSee('billing.updated');
        $this->actingAs($administrator)->get(route('documents.show', $document))->assertOk()->assertSee('document.validated');
        $this->actingAs($administrator)->get(route('dashboard.ops'))->assertOk();
    }

    public function test_comercial_cannot_access_internal_forecast_or_edit_payments(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $commercial = User::factory()->create();
        $commercial->assignRole(Role::findByName('Comercial'));
        $case = $this->case($commercial);
        BillingRecord::create([
            'case_id' => $case->id,
            'amount' => 100,
            'amount_paid' => 0,
            'invoice_status' => 'pendiente_factura',
            'payment_status' => 'pendiente',
        ]);

        $this->actingAs($commercial)
            ->get(route('inventory.forecast'))
            ->assertForbidden();
        $this->actingAs($commercial)
            ->patch(route('billing.update', $case), [
                'invoice_status' => 'facturado',
                'payment_status' => 'pendiente',
                'amount_paid' => 0,
            ])
            ->assertForbidden();
    }

    public function test_failure_audit_history_requires_audit_permission(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $commercial = User::factory()->create();
        $commercial->assignRole(Role::findByName('Comercial'));
        [, , $failure] = $this->operationalData($commercial);

        AuditLog::create([
            'user_id' => $commercial->id,
            'auditable_type' => Failure::class,
            'auditable_id' => $failure->id,
            'action' => 'failure.reported',
            'before' => [],
            'after' => ['status' => 'reportada'],
            'created_at' => now(),
        ]);

        $this->actingAs($commercial)
            ->get(route('failures.show', $failure))
            ->assertOk()
            ->assertDontSee('Historial de auditoria')
            ->assertDontSee('failure.reported');
    }

    public function test_seeded_billing_and_technical_roles_retain_required_access(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $case = $this->case(User::factory()->create());

        $cobranza = User::factory()->create();
        $cobranza->assignRole(Role::findByName('Cobranza'));
        $this->actingAs($cobranza)->get(route('billing.index'))->assertOk();

        $almacen = User::factory()->create();
        $almacen->assignRole(Role::findByName('Almacen'));
        $this->actingAs($almacen)->get(route('returns.index'))->assertOk();

        $direccionTecnica = User::factory()->create();
        $direccionTecnica->assignRole(Role::findByName('Direccion Tecnica'));
        $this->assertTrue($direccionTecnica->can('failures.release'));
        $this->assertTrue($direccionTecnica->can('documents.validate'));
    }

    public function test_audit_log_defaults_to_system_when_user_is_missing(): void
    {
        $auditLog = AuditLog::create([
            'user_id' => null,
            'action' => 'qa.audit',
            'created_at' => now(),
        ]);

        $this->assertSame('Sistema', $auditLog->user->name);
    }

    private function administrator(): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $administrator = User::factory()->create();
        $administrator->assignRole(Role::findByName('Administrador'));

        return $administrator;
    }

    private function case(User $user): SurgeryCase
    {
        $institution = Institution::create(['name' => 'Institucion QA '.uniqid(), 'active' => true]);
        $doctor = Doctor::create(['name' => 'Dr. QA '.uniqid(), 'institution_id' => $institution->id, 'active' => true]);
        $patient = Patient::create(['code' => 'QA-'.uniqid(), 'full_name' => 'Paciente QA']);
        $surgeryType = SurgeryType::create(['code' => 'QA-'.uniqid(), 'name' => 'Cirugia QA '.uniqid(), 'active' => true]);

        return SurgeryCase::create([
            'case_code' => 'MR8-QA-'.uniqid(),
            'status' => 'solicitud_registrada',
            'institution_id' => $institution->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'surgery_type_id' => $surgeryType->id,
            'scheduled_at' => now()->addDay(),
            'priority' => 'normal',
            'procedure_name' => 'Material de QA',
            'request_origin' => 'otro',
            'notes' => 'Caso para smoke tests.',
            'created_by' => $user->id,
        ]);
    }

    /** @return array{0: SurgeryCase, 1: InventoryLot, 2: Failure, 3: CaseReturn} */
    private function operationalData(User $user): array
    {
        $case = $this->case($user);
        $product = Product::create([
            'product_code' => 'QA-PROD-'.uniqid(),
            'name' => 'Producto QA',
            'classification' => 'consumible',
            'expiry_required' => true,
            'active' => true,
        ]);
        $warehouse = Warehouse::create([
            'name' => 'Almacen QA '.uniqid(),
            'type' => 'principal',
            'lead_time_hours' => 0,
            'counts_as_immediate' => true,
            'active' => true,
        ]);
        $lot = InventoryLot::create([
            'product_id' => $product->id,
            'lot' => 'QA-LOT-'.uniqid(),
            'serial' => 'QA-SER-'.uniqid(),
            'expiry' => now()->addYear(),
            'warehouse_id' => $warehouse->id,
            'quantity' => 10,
            'status' => 'apto',
            'eligible_flag' => true,
        ]);
        $failure = Failure::create([
            'case_id' => $case->id,
            'inventory_lot_id' => $lot->id,
            'product_id' => $product->id,
            'failure_type' => 'falla_mecanica',
            'occurrence_moment' => 'inventario',
            'severity' => 'media',
            'status' => 'reportada',
            'preventive_block' => false,
            'description' => 'Falla QA.',
            'reported_by' => $user->id,
        ]);
        $return = CaseReturn::create([
            'case_id' => $case->id,
            'inventory_lot_id' => $lot->id,
            'returned_qty' => 1,
            'condition' => 'pendiente_inspeccion',
        ]);

        return [$case, $lot, $failure, $return];
    }

    private function document(SurgeryCase $case, User $user): DocumentEvidence
    {
        return DocumentEvidence::create([
            'documentable_type' => SurgeryCase::class,
            'documentable_id' => $case->id,
            'document_type' => 'solicitud',
            'title' => 'Solicitud QA',
            'link_url' => 'Referencia QA.',
            'uploaded_by' => $user->id,
            'status' => 'cargado',
        ]);
    }
}
