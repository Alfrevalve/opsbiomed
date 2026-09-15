<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PilotDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PilotDemoSeeder::class);
    }

    public function test_manual_and_profile_are_available_to_all_pilot_roles_but_admin_manual_is_restricted(): void
    {
        foreach ($this->pilotUsers() as $email => $user) {
            $this->actingAs($user)->get(route('manual.index'))->assertOk();
            $this->actingAs($user)->get(route('profile.edit'))->assertOk();

            $response = $this->actingAs($user)->get(route('admin.manual.index'));
            in_array($email, ['admin@ops.test', 'dt@ops.test'], true)
                ? $response->assertOk()
                : $response->assertForbidden();
        }
    }

    public function test_operational_and_financial_permissions_match_pilot_roles(): void
    {
        $users = $this->pilotUsers();

        $this->actingAs($users['jefe.linea@ops.test'])->get(route('inventory.forecast'))->assertOk();
        $this->actingAs($users['jefe.linea@ops.test'])->get(route('approvals.cost-zero.index'))->assertOk();
        $this->actingAs($users['dt@ops.test'])->get(route('failures.index'))->assertOk();
        $this->actingAs($users['dt@ops.test'])->get(route('returns.index'))->assertOk();
        $this->actingAs($users['almacen@ops.test'])->get(route('inventory.index'))->assertOk();
        $this->actingAs($users['almacen@ops.test'])->get(route('returns.index'))->assertOk();
        $this->actingAs($users['instrumentista@ops.test'])->get(route('trace.index'))->assertOk();
        $this->actingAs($users['cobranza@ops.test'])->get(route('billing.index'))->assertOk();
        $this->actingAs($users['cobranza@ops.test'])->get(route('reports.billing'))->assertOk();
        $this->actingAs($users['gerencia@ops.test'])->get(route('approvals.cost-zero.index'))->assertOk();
        $this->actingAs($users['comercial@ops.test'])->get(route('reports.commercial'))->assertOk();
        $this->actingAs($users['comercial@ops.test'])->get(route('inventory.forecast'))->assertForbidden();
        $this->actingAs($users['comercial@ops.test'])->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($users['almacen@ops.test'])->get(route('billing.index'))->assertForbidden();
        $this->actingAs($users['instrumentista@ops.test'])->get(route('billing.index'))->assertForbidden();
    }

    public function test_commercial_read_access_does_not_expose_billing_amounts(): void
    {
        $user = $this->pilotUsers()['comercial@ops.test'];

        $this->actingAs($user)
            ->get(route('billing.index'))
            ->assertOk()
            ->assertDontSee('S/ 780')
            ->assertDontSee('780.00');
    }

    /** @return array<string, User> */
    private function pilotUsers(): array
    {
        return User::query()
            ->whereIn('email', [
                'admin@ops.test',
                'jefe.linea@ops.test',
                'dt@ops.test',
                'almacen@ops.test',
                'instrumentista@ops.test',
                'comercial@ops.test',
                'cobranza@ops.test',
                'gerencia@ops.test',
            ])
            ->get()
            ->keyBy('email')
            ->all();
    }
}
