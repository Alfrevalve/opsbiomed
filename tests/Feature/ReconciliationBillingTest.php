<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Models\Approval;
use App\Models\BillingRecord;
use App\Models\CaseMaterialUsed;
use App\Models\CaseValuation;
use App\Models\CaseValuationLine;
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

class ReconciliationBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciliation_is_completed_when_quantities_match(): void
    {
        $user = $this->userWithRole('Almacen', ['dashboard.view', 'cases.view', 'cases.close']);
        [$case, $reservation] = $this->closedCase($user);
        CaseMaterialUsed::create($this->materialAttributes($case, $reservation, [
            'reserved_qty' => 3,
            'used_qty' => 2,
            'returned_qty' => 1,
        ]));

        $this->actingAs($user)
            ->post(route('cases.reconciliation.store', $case), ['observations' => 'Conciliacion sin diferencias.'])
            ->assertRedirect(route('cases.show', $case));

        $this->assertDatabaseHas('case_reconciliations', [
            'case_id' => $case->id,
            'status' => 'conciliado',
            'total_reserved' => 3,
            'total_difference' => 0,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'reconciliation.completed',
            'auditable_id' => $case->id,
        ]);
    }

    public function test_reconciliation_blocks_a_difference_without_reason(): void
    {
        $user = $this->userWithRole('Almacen', ['dashboard.view', 'cases.view', 'cases.close']);
        [$case, $reservation] = $this->closedCase($user);
        CaseMaterialUsed::create($this->materialAttributes($case, $reservation, [
            'reserved_qty' => 3,
            'used_qty' => 2,
            'difference_qty' => 1,
        ]));

        $this->actingAs($user)
            ->post(route('cases.reconciliation.store', $case))
            ->assertRedirect()
            ->assertSessionHasErrors('reconciliation');

        $this->assertDatabaseCount('case_reconciliations', 0);
    }

    public function test_only_authorized_role_can_approve_cost_zero(): void
    {
        $requester = $this->userWithRole('Instrumentista', ['dashboard.view', 'cases.view']);
        [$case, $reservation] = $this->closedCase($requester);
        $valuation = $this->costZeroValuation($case, $reservation, $requester);
        $approval = Approval::create([
            'case_id' => $case->id,
            'valuation_id' => $valuation->id,
            'type' => 'cost_zero',
            'status' => 'pendiente_aprobacion',
            'requested_by' => $requester->id,
        ]);

        $unauthorized = $this->userWithRole('Almacen', ['dashboard.view', 'cases.view']);
        $this->actingAs($unauthorized)
            ->post(route('approvals.cost-zero.approve', $valuation))
            ->assertForbidden();

        $approver = $this->userWithRole('Gerencia', ['dashboard.view', 'cases.view', 'approvals.approve', 'billing.view']);
        $this->actingAs($approver)
            ->post(route('approvals.cost-zero.approve', $valuation), ['evidence' => 'Aprobacion gerencial'])
            ->assertRedirect(route('approvals.cost-zero.index'));

        $this->assertDatabaseHas('approvals', ['id' => $approval->id, 'status' => 'aprobado', 'approved_by' => $approver->id]);
        $this->assertDatabaseHas('case_valuations', ['id' => $valuation->id, 'status' => 'costo_cero_aprobado']);
        $this->assertDatabaseHas('billing_records', [
            'case_id' => $case->id,
            'invoice_status' => 'costo_cero_aprobado',
            'amount' => 0,
            'payment_status' => 'pagado',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'cost_zero.approved']);
    }

    public function test_rejecting_cost_zero_returns_valuation_to_correction(): void
    {
        $requester = $this->userWithRole('Instrumentista', ['dashboard.view', 'cases.view']);
        [$case, $reservation] = $this->closedCase($requester);
        $valuation = $this->costZeroValuation($case, $reservation, $requester);
        Approval::create([
            'case_id' => $case->id,
            'valuation_id' => $valuation->id,
            'type' => 'cost_zero',
            'status' => 'pendiente_aprobacion',
            'requested_by' => $requester->id,
        ]);
        $approver = $this->userWithRole('Jefe de Linea', ['dashboard.view', 'cases.view', 'approvals.approve']);

        $this->actingAs($approver)
            ->post(route('approvals.cost-zero.reject', $valuation), ['reason' => 'Cargar precio institucional.'])
            ->assertRedirect(route('approvals.cost-zero.index'));

        $this->assertDatabaseHas('case_valuations', ['id' => $valuation->id, 'status' => 'pendiente_valorizacion']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'cost_zero.rejected']);
    }

    public function test_invoice_number_is_required_to_mark_billing_as_invoiced(): void
    {
        $user = $this->userWithRole('Cobranza', ['dashboard.view', 'cases.view', 'billing.view', 'billing.update']);
        [$case] = $this->closedCase($user);
        BillingRecord::create(['case_id' => $case->id, 'amount' => 100, 'amount_paid' => 0]);

        $this->actingAs($user)
            ->patch(route('billing.update', $case), [
                'invoice_status' => 'facturado',
                'payment_status' => 'pendiente',
                'amount_paid' => 0,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('invoice_number');
    }

    public function test_payment_cannot_be_marked_paid_with_an_open_balance(): void
    {
        $user = $this->userWithRole('Cobranza', ['dashboard.view', 'cases.view', 'billing.view', 'billing.update']);
        [$case] = $this->closedCase($user);
        BillingRecord::create(['case_id' => $case->id, 'amount' => 100, 'amount_paid' => 0, 'invoice_status' => 'facturado']);

        $this->actingAs($user)
            ->patch(route('billing.update', $case), [
                'invoice_status' => 'facturado',
                'invoice_number' => 'F001-0001',
                'payment_status' => 'pagado',
                'amount_paid' => 20,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('billing');
    }

    public function test_overdue_payment_is_detected_on_billing_and_dashboard(): void
    {
        $user = $this->userWithRole('Cobranza', ['dashboard.view', 'cases.view', 'billing.view', 'billing.update']);
        [$case] = $this->closedCase($user);
        BillingRecord::create([
            'case_id' => $case->id,
            'amount' => 100,
            'amount_paid' => 25,
            'invoice_status' => 'facturado',
            'invoice_number' => 'F001-0002',
            'due_date' => today()->subDay(),
            'payment_status' => 'pendiente',
        ]);

        $this->actingAs($user)
            ->get(route('billing.index'))
            ->assertOk()
            ->assertSee('vencido');

        $this->assertDatabaseHas('billing_records', [
            'case_id' => $case->id,
            'payment_status' => 'vencido',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard.ops'))
            ->assertOk()
            ->assertSee('Deuda vencida')
            ->assertSee('S/ 75.00');
    }

    public function test_approved_cost_zero_does_not_count_as_debt(): void
    {
        $user = $this->userWithRole('Cobranza', ['dashboard.view', 'cases.view', 'billing.view']);
        [$case] = $this->closedCase($user);
        BillingRecord::create([
            'case_id' => $case->id,
            'amount' => 0,
            'amount_paid' => 0,
            'invoice_status' => 'costo_cero_aprobado',
            'payment_status' => 'pagado',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard.ops'))
            ->assertOk()
            ->assertSee('Total valorizado pendiente')
            ->assertSee('S/ 0.00');
    }

    /**
     * @return array{0: SurgeryCase, 1: Reservation}
     */
    private function closedCase(User $user): array
    {
        $institution = Institution::create(['name' => 'Institucion administrativa '.uniqid(), 'active' => true]);
        $doctor = Doctor::create(['institution_id' => $institution->id, 'name' => 'Dr. Administrativo '.uniqid(), 'active' => true]);
        $patient = Patient::create(['code' => 'PAT-'.uniqid(), 'full_name' => 'Paciente administrativo']);
        $type = SurgeryType::create(['code' => 'admin_'.uniqid(), 'name' => 'Cirugia administrativa '.uniqid(), 'active' => true]);
        $case = SurgeryCase::create([
            'case_code' => 'MR8-ADMIN-'.uniqid(),
            'status' => CaseStatus::Cerrado,
            'institution_id' => $institution->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'surgery_type_id' => $type->id,
            'scheduled_at' => now()->subDay(),
            'priority' => 'normal',
            'procedure_name' => 'Material administrativo MR8',
            'request_origin' => 'whatsapp',
            'created_by' => $user->id,
        ]);
        $warehouse = Warehouse::create([
            'name' => 'Principal administrativo '.uniqid(),
            'type' => 'principal',
            'lead_time_hours' => 0,
            'counts_as_immediate' => true,
            'active' => true,
        ]);
        $product = Product::create([
            'product_line' => 'MR8',
            'family' => 'Fresas',
            'subfamily' => 'Administrativo',
            'product_code' => 'ADMIN-'.uniqid(),
            'name' => 'Producto administrativo',
            'classification' => 'consumible',
            'expiry_required' => false,
            'tracking_type' => 'lot',
            'active' => true,
        ]);
        $lot = InventoryLot::create([
            'product_id' => $product->id,
            'lot' => 'LOT-ADMIN-'.uniqid(),
            'warehouse_id' => $warehouse->id,
            'quantity' => 10,
            'expiry' => today()->addYear(),
            'status' => 'apto',
            'eligible_flag' => true,
        ]);
        $reservation = Reservation::create([
            'case_id' => $case->id,
            'inventory_lot_id' => $lot->id,
            'quantity' => 3,
            'status' => 'consumed',
            'reserved_by' => $user->id,
        ]);

        return [$case, $reservation];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function materialAttributes(SurgeryCase $case, Reservation $reservation, array $overrides = []): array
    {
        return array_merge([
            'case_id' => $case->id,
            'reservation_id' => $reservation->id,
            'inventory_lot_id' => $reservation->inventory_lot_id,
            'reserved_qty' => 3,
            'used_qty' => 0,
            'unused_opened_qty' => 0,
            'returned_qty' => 0,
            'failure_qty' => 0,
            'difference_qty' => 0,
            'reported_by' => $case->created_by,
        ], $overrides);
    }

    private function costZeroValuation(SurgeryCase $case, Reservation $reservation, User $user): CaseValuation
    {
        $valuation = CaseValuation::create([
            'case_id' => $case->id,
            'subtotal' => 0,
            'total' => 0,
            'status' => 'costo_cero_pendiente_aprobacion',
            'created_by' => $user->id,
        ]);
        CaseValuationLine::create([
            'case_valuation_id' => $valuation->id,
            'case_id' => $case->id,
            'reservation_id' => $reservation->id,
            'product_id' => $reservation->inventoryLot->product_id,
            'inventory_lot_id' => $reservation->inventory_lot_id,
            'quantity_used' => 1,
            'unit_price' => 0,
            'subtotal' => 0,
            'cost_zero' => true,
            'cost_zero_reason' => 'Muestra institucional autorizada.',
            'requires_approval' => true,
        ]);

        return $valuation;
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWithRole(string $roleName, array $permissions): User
    {
        $role = Role::findOrCreate($roleName);
        $role->syncPermissions(collect($permissions)->map(fn (string $permission) => Permission::findOrCreate($permission)));
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
