<?php

namespace Tests\Feature;

use App\Models\ManualArticle;
use App\Models\User;
use Database\Seeders\ManualContentSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManualTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('manual.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_read_manual_in_operational_layout(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(ManualContentSeeder::class);
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('Programador Quirurgico'));

        $response = $this->actingAs($user)->get(route('manual.index'));

        $response->assertOk()
            ->assertSee('Manual Operativo OPS BIOMED MR8')
            ->assertSee('Crear una solicitud quirúrgica')
            ->assertSee('Ayuda y manual')
            ->assertSee('data-sidebar-section="manual"', false)
            ->assertDontSee('Paciente Demo MR8');
    }

    public function test_manual_hides_modules_without_operational_permission(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(ManualContentSeeder::class);
        $role = Role::findOrCreate('Manual Reader QA');
        $role->syncPermissions([Permission::findByName('manual.view')]);
        $user = User::factory()->create();
        $user->assignRole($role);

        $response = $this->actingAs($user)->get(route('manual.index'));

        $response->assertOk()
            ->assertSee('Manual Operativo OPS BIOMED MR8')
            ->assertSee('Seguridad y buenas prácticas operativas')
            ->assertDontSee('Crear una solicitud quirúrgica')
            ->assertDontSee('href="'.route('manual.show', 'consultar-solicitudes').'"', false);
        $this->actingAs($user)->get(route('manual.show', 'crear-solicitud-quirurgica'))->assertForbidden();
    }

    public function test_manual_search_finds_titles_and_keywords(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(ManualContentSeeder::class);
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('Administrador'));

        $this->actingAs($user)
            ->get(route('manual.search', ['q' => 'trazabilidad']))
            ->assertOk()
            ->assertSee('Escanear un código trazable')
            ->assertDontSee('Administrar usuarios y permisos');
    }

    public function test_manual_progress_is_recorded_without_operational_data(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(ManualContentSeeder::class);
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('Administrador'));
        $article = ManualArticle::query()->where('slug', 'crear-solicitud-quirurgica')->firstOrFail();

        $this->actingAs($user)->get(route('manual.show', $article->slug))->assertOk();
        $this->assertDatabaseHas('manual_progress', [
            'user_id' => $user->id,
            'article_id' => $article->id,
            'completed_at' => null,
        ]);

        $this->actingAs($user)
            ->post(route('manual.read', $article))
            ->assertRedirect(route('manual.show', $article->slug));

        $this->assertDatabaseMissing('manual_progress', [
            'user_id' => $user->id,
            'article_id' => $article->id,
            'completed_at' => null,
        ]);
        $this->assertDatabaseCount('manual_progress', 1);
    }

    public function test_recommended_filter_has_content_and_tutorial_module_links_are_valid(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(ManualContentSeeder::class);
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('Administrador'));

        $this->actingAs($user)
            ->get(route('manual.index', ['recommended' => 1]))
            ->assertOk()
            ->assertSee('Iniciar sesión y cerrar sesión');

        ManualArticle::query()
            ->whereNotNull('route_name')
            ->get()
            ->each(function (ManualArticle $article) use ($user): void {
                if (Route::has($article->route_name)) {
                    $this->actingAs($user)
                        ->get(route('manual.show', $article->slug))
                        ->assertOk();
                }
            });
    }

    public function test_manual_permissions_are_assigned_to_real_roles(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $this->assertTrue(Role::findByName('Administrador')->hasPermissionTo('manual.view'));
        $this->assertTrue(Role::findByName('Administrador')->hasPermissionTo('manual.manage'));
        $this->assertTrue(Role::findByName('Direccion Tecnica')->hasPermissionTo('manual.manage'));
        $this->assertTrue(Role::findByName('Instrumentista')->hasPermissionTo('manual.view'));
        $this->assertFalse(Role::findByName('Instrumentista')->hasPermissionTo('manual.manage'));
    }
}
