<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Models\Approval;
use App\Models\BillingRecord;
use App\Models\CaseValuation;
use App\Models\Institution;
use App\Models\SurgeryCase;
use App\Models\SurgeryType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdministrativeScreensTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_and_billing_screens_render_for_authorized_user(): void
    {
        $permissions = collect([
            'dashboard.view',
            'cases.view',
            'billing.view',
            'approvals.approve',
        ])->map(fn (string $permission) => Permission::findOrCreate($permission));
        $role = Role::findOrCreate('Gerencia');
        $role->syncPermissions($permissions);
        $user = User::factory()->create();
        $user->assignRole($role);

        $institution = Institution::create(['name' => 'Institucion pantallas '.uniqid(), 'active' => true]);
        $type = SurgeryType::create(['code' => 'pantallas_'.uniqid(), 'name' => 'Cirugia pantallas '.uniqid(), 'active' => true]);
        $case = SurgeryCase::create([
            'case_code' => 'MR8-SCREEN-'.uniqid(),
            'status' => CaseStatus::Cerrado,
            'institution_id' => $institution->id,
            'surgery_type_id' => $type->id,
            'scheduled_at' => now()->subDay(),
            'procedure_name' => 'Material de prueba',
            'request_origin' => 'whatsapp',
            'created_by' => $user->id,
        ]);
        $valuation = CaseValuation::create([
            'case_id' => $case->id,
            'subtotal' => 0,
            'total' => 0,
            'status' => 'costo_cero_pendiente_aprobacion',
            'created_by' => $user->id,
        ]);
        Approval::create([
            'case_id' => $case->id,
            'valuation_id' => $valuation->id,
            'type' => 'cost_zero',
            'status' => 'pendiente_aprobacion',
            'requested_by' => $user->id,
        ]);
        BillingRecord::create([
            'case_id' => $case->id,
            'amount' => 0,
            'amount_paid' => 0,
            'invoice_status' => 'costo_cero_pendiente_aprobacion',
            'payment_status' => 'pendiente',
        ]);

        $this->actingAs($user)
            ->get(route('approvals.cost-zero.index'))
            ->assertOk()
            ->assertSee('Aprobaciones de costo cero')
            ->assertSee($case->case_code);

        $this->actingAs($user)
            ->get(route('billing.show', $case))
            ->assertOk()
            ->assertSee('Facturacion y cobranza')
            ->assertSee('costo_cero_pendiente_aprobacion');
    }
}
