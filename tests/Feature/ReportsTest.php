<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Models\BillingRecord;
use App\Models\CaseMaterialUsed;
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
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_load_the_report_index_and_all_sections(): void
    {
        $user = $this->reportUser([
            'reports.view',
            'reports.operations',
            'reports.commercial',
            'reports.billing',
            'reports.inventory',
        ]);

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Reportes OPS BIOMED')
            ->assertSee('Reporte operativo')
            ->assertSee('Reporte comercial')
            ->assertSee('Facturacion y cobranza')
            ->assertSee('Reporte de inventario');

        foreach (['operations', 'commercial', 'billing', 'inventory'] as $section) {
            $this->actingAs($user)
                ->get(route('reports.'.$section))
                ->assertOk();
        }
    }

    public function test_operations_report_filters_surgeries_by_date(): void
    {
        $user = $this->reportUser(['reports.operations']);
        $this->caseFixture($user, now()->setDate(2026, 9, 10)->setTime(10, 0));
        $this->caseFixture($user, now()->setDate(2026, 8, 10)->setTime(10, 0));

        $response = $this->actingAs($user)
            ->get(route('reports.operations', [
                'date_from' => '2026-09-01',
                'date_to' => '2026-09-30',
            ]));

        $response->assertOk()->assertSee('10/09/2026')->assertDontSee('10/08/2026');
    }

    public function test_commercial_report_and_csv_export_include_consumption(): void
    {
        $user = $this->reportUser(['reports.commercial', 'reports.export']);
        [$case, $lot] = $this->caseFixture($user);
        CaseMaterialUsed::create([
            'case_id' => $case->id,
            'inventory_lot_id' => $lot->id,
            'used_qty' => 2,
            'subtotal' => 240,
            'reported_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('reports.commercial'))
            ->assertOk()
            ->assertSee('Productos mas usados')
            ->assertSee($lot->product->product_code);

        $response = $this->actingAs($user)
            ->get(route('reports.export', ['type' => 'commercial']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString($lot->product->product_code, $response->streamedContent());
    }

    public function test_billing_report_shows_pending_and_overdue_debt(): void
    {
        $user = $this->reportUser(['reports.billing']);
        [$case] = $this->caseFixture($user, now()->subDays(2));
        BillingRecord::create([
            'case_id' => $case->id,
            'amount' => 1000,
            'amount_paid' => 250,
            'invoice_status' => 'facturado',
            'invoice_number' => 'F001-REPORT',
            'due_date' => today()->subDays(10),
            'payment_status' => 'vencido',
        ]);

        $this->actingAs($user)
            ->get(route('reports.billing'))
            ->assertOk()
            ->assertSee('Monto pendiente')
            ->assertSee('S/ 750.00')
            ->assertSee('Deuda por antiguedad')
            ->assertSee('0-30 dias');
    }

    public function test_inventory_report_filters_by_product_and_stock_urgency(): void
    {
        $user = $this->reportUser(['reports.inventory']);
        [$case, $lot] = $this->caseFixture($user);
        $lot->update(['quantity' => 2]);
        $lot->product->update([
            'length_cm' => 9,
            'diameter_mm' => 2,
            'cut_type' => 'cortante',
            'component_type' => 'fresa',
        ]);
        KitRule::create([
            'surgery_type_id' => $case->surgery_type_id,
            'length_cm' => 9,
            'diameter_mm' => 2,
            'cut_type' => 'cortante',
            'component_type' => 'fresa',
            'min_qty' => 3,
            'target_qty' => 5,
            'criticality' => 'critica',
            'required' => true,
        ]);

        $this->actingAs($user)
            ->get(route('reports.inventory', [
                'product_id' => $lot->product_id,
                'urgency' => 'critical',
            ]))
            ->assertOk()
            ->assertSee($lot->product->product_code)
            ->assertSee('Comprar urgente');
    }

    public function test_user_without_report_permission_cannot_access_reports(): void
    {
        $user = $this->reportUser(['dashboard.view', 'cases.view']);

        $this->actingAs($user)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($user)->get(route('reports.operations'))->assertForbidden();
    }

    public function test_report_permissions_are_seeded_for_operational_roles(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $this->assertTrue(Role::findByName('Administrador')->hasPermissionTo('reports.export'));
        $this->assertTrue(Role::findByName('Jefe de Linea')->hasPermissionTo('reports.billing'));
        $this->assertTrue(Role::findByName('Comercial')->hasPermissionTo('reports.commercial'));
        $this->assertTrue(Role::findByName('Cobranza')->hasPermissionTo('reports.billing'));
        $this->assertFalse(Role::findByName('Almacen')->hasPermissionTo('reports.billing'));
    }

    public function test_dashboard_exposes_monthly_kpis_and_report_access(): void
    {
        $user = $this->reportUser(['dashboard.view', 'cases.view', 'reports.view', 'commercial.view', 'inventory.view']);
        $this->caseFixture($user, now());

        $this->actingAs($user)
            ->get(route('dashboard.ops'))
            ->assertOk()
            ->assertSee('Reportes ejecutivos')
            ->assertSee('Cirugias del mes')
            ->assertSee('Productos criticos bajo minimo');
    }

    /**
     * @param  list<string>  $permissions
     */
    private function reportUser(array $permissions): User
    {
        $role = Role::findOrCreate('reports-test-'.uniqid());
        $role->syncPermissions(collect($permissions)->map(
            fn (string $permission): Permission => Permission::findOrCreate($permission),
        ));
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /**
     * @return array{0: SurgeryCase, 1: InventoryLot}
     */
    private function caseFixture(User $user, ?Carbon $scheduledAt = null): array
    {
        $institution = Institution::create([
            'name' => 'Institucion reportes '.uniqid(),
            'active' => true,
        ]);
        $doctor = Doctor::create([
            'institution_id' => $institution->id,
            'name' => 'Dr. Reportes '.uniqid(),
            'active' => true,
        ]);
        $patient = Patient::create([
            'code' => 'PAT-REPORT-'.uniqid(),
            'full_name' => 'Paciente de reportes',
        ]);
        $surgeryType = SurgeryType::create([
            'code' => 'reports-'.uniqid(),
            'name' => 'Cirugia de reportes '.uniqid(),
            'active' => true,
        ]);
        $case = SurgeryCase::create([
            'case_code' => 'MR8-REPORT-'.uniqid(),
            'status' => CaseStatus::Cerrado,
            'institution_id' => $institution->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'surgery_type_id' => $surgeryType->id,
            'scheduled_at' => $scheduledAt ?? now()->subDay(),
            'priority' => 'normal',
            'procedure_name' => 'Caso de reportes',
            'request_origin' => 'whatsapp',
            'created_by' => $user->id,
        ]);
        $warehouse = Warehouse::create([
            'name' => 'Almacen reportes '.uniqid(),
            'type' => 'principal',
            'lead_time_hours' => 0,
            'counts_as_immediate' => true,
            'active' => true,
        ]);
        $product = Product::create([
            'product_line' => 'MR8',
            'family' => 'Fresas',
            'subfamily' => 'Reportes',
            'product_code' => 'MR8-REPORT-PRODUCT-'.uniqid(),
            'name' => 'Producto de reportes',
            'classification' => 'consumible',
            'expiry_required' => false,
            'tracking_type' => 'lot',
            'active' => true,
        ]);
        $lot = InventoryLot::create([
            'product_id' => $product->id,
            'lot' => 'LOT-REPORT-'.uniqid(),
            'warehouse_id' => $warehouse->id,
            'quantity' => 10,
            'expiry' => today()->addYear(),
            'status' => 'apto',
            'eligible_flag' => true,
        ]);

        return [$case, $lot];
    }
}
