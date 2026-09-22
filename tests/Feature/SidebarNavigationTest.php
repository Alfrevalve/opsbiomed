<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_sees_all_sidebar_areas(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $administrator = User::factory()->create();
        $administrator->assignRole(Role::findByName('Administrador'));

        $response = $this->actingAs($administrator)->get(route('dashboard.ops'));

        $response->assertOk();
        foreach ([
            'dashboard.ops',
            'schedule.index',
            'trace.index',
            'cases.index',
            'returns.index',
            'failures.index',
            'documents.index',
            'inventory.index',
            'inventory.coverage',
            'inventory.forecast',
            'catalog.imports.index',
            'billing.index',
            'approvals.cost-zero.index',
            'reports.index',
            'masters.institutions.index',
            'masters.doctors.index',
            'masters.prices.index',
            'admin.users.index',
            'compliance.ai-policy',
            'manual.index',
            'manual.quick-start',
        ] as $routeName) {
            $response->assertSee('href="'.route($routeName).'"', false);
        }

        $response->assertSee('Navegacion principal');
        $response->assertSee('data-sidebar-section="operation"', false);
        $response->assertSee('data-sidebar-section="inventory"', false);
        $response->assertSee('data-sidebar-section="commercial"', false);
        $response->assertSee('data-sidebar-section="masters"', false);
        $response->assertSee('data-sidebar-section="administration"', false);
        $response->assertSee('data-sidebar-section="compliance"', false);
        $response->assertSee('data-sidebar-section="manual"', false);
        $response->assertSee('data-sidebar-chevron', false);
        $response->assertSee('ops-biomed-logo.png', false);
        $response->assertSee('Mi perfil');
        $response->assertSee('Cerrar sesion');
        $response->assertSee('data-sidebar-toggle', false);
        $response->assertSee('data-sidebar-close', false);
        $response->assertSee('data-ops-clock-time', false);
        $response->assertSee('data-ops-clock-date', false);
        $response->assertSee('LIMA');
        $response->assertDontSee('ops-user-link', false);
        $response->assertDontSee('ops-topbar-logout', false);
        $response->assertDontSee('Laravel');
        $response->assertSee('Comercial y cobranza');
        $response->assertSee('Inventario MR8');
        $response->assertSee('Administracion');
        $response->assertSee('Cumplimiento');
    }

    public function test_sidebar_hides_links_without_their_permissions(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $role = Role::findOrCreate('Sidebar QA');
        $role->syncPermissions([
            Permission::findOrCreate('dashboard.view'),
            Permission::findOrCreate('cases.view'),
            Permission::findOrCreate('inventory.view'),
        ]);
        $user = User::factory()->create();
        $user->assignRole($role);

        $response = $this->actingAs($user)->get(route('dashboard.ops'));

        $response->assertOk()
            ->assertSee('href="'.route('dashboard.ops').'"', false)
            ->assertSee('href="'.route('cases.index').'"', false)
            ->assertSee('href="'.route('inventory.index').'"', false)
            ->assertSee('href="'.route('inventory.coverage').'"', false)
            ->assertDontSee('href="'.route('schedule.index').'"', false)
            ->assertDontSee('href="'.route('trace.index').'"', false)
            ->assertDontSee('href="'.route('inventory.forecast').'"', false)
            ->assertDontSee('href="'.route('billing.index').'"', false)
            ->assertDontSee('href="'.route('admin.users.index').'"', false)
            ->assertDontSee('href="'.route('compliance.ai-policy').'"', false)
            ->assertDontSee('href="'.route('masters.institutions.index').'"', false)
            ->assertDontSee('href="'.route('manual.index').'"', false);
    }
}
