<?php

namespace Tests\Feature;

use App\Models\CaseReturn;
use App\Models\DocumentEvidence;
use App\Models\Failure;
use App\Models\InventoryLot;
use App\Models\OperationalAlert;
use App\Models\SurgeryCase;
use App\Models\User;
use Database\Seeders\PilotDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PilotQaSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PilotDemoSeeder::class);
    }

    public function test_all_eight_pilot_users_can_login_and_open_dashboard(): void
    {
        $emails = [
            'admin@ops.test',
            'jefe.linea@ops.test',
            'dt@ops.test',
            'almacen@ops.test',
            'instrumentista@ops.test',
            'comercial@ops.test',
            'cobranza@ops.test',
            'gerencia@ops.test',
        ];

        foreach ($emails as $email) {
            $this->post(route('login'), [
                'email' => $email,
                'password' => (string) env('PILOT_DEMO_PASSWORD'),
            ])->assertRedirect(route('dashboard'));

            $this->get(route('dashboard.ops'))->assertOk();
            $this->post(route('logout'))->assertRedirect('/');
        }
    }

    public function test_administrator_can_open_pilot_routes_without_server_errors(): void
    {
        $admin = User::query()->where('email', 'admin@ops.test')->firstOrFail();
        $case = SurgeryCase::query()->where('case_code', 'MR8-PILOT-001')->firstOrFail();
        $lot = InventoryLot::query()->where('lot', 'PILOT-VERDE-9-3')->firstOrFail();
        $failure = Failure::query()->where('description', 'like', 'Falla tecnica demo:%')->firstOrFail();
        $return = CaseReturn::query()->where('case_id', $case->id)->firstOrFail();
        $document = DocumentEvidence::query()->where('documentable_id', $case->id)->firstOrFail();

        $routes = [
            route('dashboard.ops'),
            route('cases.index'),
            route('cases.show', $case),
            route('cases.control', $case),
            route('schedule.index'),
            route('schedule.conflicts'),
            route('inventory.index'),
            route('inventory.show', $lot),
            route('inventory.coverage'),
            route('inventory.forecast'),
            route('catalog.imports.index'),
            route('failures.index'),
            route('failures.show', $failure),
            route('returns.index'),
            route('returns.show', $return),
            route('documents.index'),
            route('documents.show', $document),
            route('billing.index'),
            route('billing.show', $case),
            route('approvals.cost-zero.index'),
            route('reports.index'),
            route('reports.operations'),
            route('reports.commercial'),
            route('reports.billing'),
            route('reports.inventory'),
            route('manual.index'),
            route('trace.index'),
            route('profile.edit'),
            route('admin.users.index'),
            route('masters.institutions.index'),
            route('masters.doctors.index'),
            route('masters.prices.index'),
        ];

        foreach ($routes as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_pilot_records_are_visible_in_operational_views(): void
    {
        $admin = User::query()->where('email', 'admin@ops.test')->firstOrFail();
        $case = SurgeryCase::query()->where('case_code', 'MR8-PILOT-001')->firstOrFail();

        $this->actingAs($admin)->get(route('dashboard.ops'))
            ->assertOk()
            ->assertSee('MR8-PILOT-001');

        $this->actingAs($admin)->get(route('cases.index'))
            ->assertOk()
            ->assertSee('MR8-PILOT-001');

        $this->actingAs($admin)->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('MR8-9BA30');

        $this->actingAs($admin)->get(route('failures.index'))
            ->assertOk()
            ->assertSee('Vibracion')
            ->assertSee('MR8-10BA30D')
            ->assertSee('PILOT-BLOQUEADO-10-3-D');

        $this->actingAs($admin)->get(route('returns.index'))
            ->assertOk()
            ->assertSee('MR8-PILOT-001');

        $this->actingAs($admin)->get(route('documents.index'))
            ->assertOk()
            ->assertSee('Solicitud quirurgica piloto');

        $this->actingAs($admin)->get(route('billing.index'))
            ->assertOk()
            ->assertSee('pendiente_oc');

        $this->actingAs($admin)->get(route('manual.index'))
            ->assertOk()
            ->assertSee('Manual Operativo OPS BIOMED MR8');

        $this->assertDatabaseHas('reservations', ['case_id' => $case->id, 'status' => 'active']);
        $this->assertDatabaseHas('inventory_lots', ['lot' => 'PILOT-YSAN-9-3']);
        $this->assertDatabaseHas('inventory_lots', ['lot' => 'PILOT-VENCIDO-10-2']);
        $this->assertDatabaseHas('inventory_lots', ['lot' => 'PILOT-DEVOLUCION-CUARENTENA']);
        $this->assertDatabaseHas('billing_records', ['case_id' => $case->id, 'invoice_status' => 'pendiente_oc']);
    }

    public function test_alert_evaluation_generates_records_and_is_visible(): void
    {
        $this->artisan('ops:alerts:evaluate')->assertExitCode(0);
        $this->assertGreaterThan(0, OperationalAlert::query()->count());

        $admin = User::query()->where('email', 'admin@ops.test')->firstOrFail();
        $this->actingAs($admin)->get(route('alerts.index'))->assertOk();
    }

    public function test_inactive_pilot_user_is_blocked_by_active_user_middleware(): void
    {
        $user = User::query()->where('email', 'instrumentista@ops.test')->firstOrFail();
        $user->update(['active' => false]);

        $this->actingAs($user)->get(route('dashboard.ops'))->assertForbidden();
    }
}
