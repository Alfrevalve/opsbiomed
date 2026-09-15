<?php

namespace Tests\Feature;

use App\Models\ManualArticle;
use App\Models\ManualCategory;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManualAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_and_technical_direction_can_access_manual_administration(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        foreach (['Administrador', 'Direccion Tecnica'] as $roleName) {
            $user = $this->userWithRole($roleName);

            $this->actingAs($user)->get(route('admin.manual.index'))->assertOk();
        }
    }

    public function test_other_roles_cannot_access_manual_administration(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = $this->userWithRole('Instrumentista');

        $this->actingAs($user)->get(route('admin.manual.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.manual.create'))->assertForbidden();
    }

    public function test_administrator_can_create_edit_publish_and_disable_a_tutorial(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = $this->userWithRole('Administrador');
        $category = $this->category();
        $payload = $this->articlePayload($category);

        $this->actingAs($user)
            ->post(route('admin.manual.store'), $payload)
            ->assertRedirect();

        $article = ManualArticle::query()->where('slug', 'validar-inventario-demo')->firstOrFail();
        $this->assertFalse($article->active);
        $this->assertSame(['Administrador'], $article->roles_json);
        $this->assertDatabaseHas('audit_logs', ['action' => 'manual.article.created', 'auditable_id' => $article->id]);

        $this->actingAs($user)
            ->put(route('admin.manual.update', $article), array_merge($payload, [
                'title' => 'Validar inventario demo actualizado',
                'status' => 'published',
            ]))
            ->assertRedirect(route('admin.manual.edit', $article));

        $article->refresh();
        $this->assertTrue($article->active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'manual.article.updated', 'auditable_id' => $article->id]);

        $this->actingAs($user)
            ->get(route('manual.show', $article->slug))
            ->assertOk()
            ->assertSee('Validar inventario demo actualizado');

        $this->actingAs($user)
            ->patch(route('admin.manual.disable', $article))
            ->assertRedirect();

        $article->refresh();
        $this->assertFalse($article->active);
        $this->actingAs($user)->get(route('manual.show', $article->slug))->assertNotFound();
        $this->assertDatabaseHas('audit_logs', ['action' => 'manual.article.disabled', 'auditable_id' => $article->id]);
    }

    public function test_slug_must_be_unique_and_published_delete_requires_confirmation(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = $this->userWithRole('Administrador');
        $category = $this->category();
        $article = ManualArticle::query()->create([
            'category_id' => $category->id,
            'title' => 'Tutorial existente',
            'slug' => 'slug-existente',
            'summary' => 'Resumen',
            'level' => 'basico',
            'estimated_minutes' => 5,
            'active' => true,
            'version' => '1.0',
            'published_at' => now(),
        ]);

        $duplicate = array_merge($this->articlePayload($category), ['slug' => 'slug-existente']);
        $this->actingAs($user)
            ->post(route('admin.manual.store'), $duplicate)
            ->assertSessionHasErrors('slug');

        $this->actingAs($user)
            ->delete(route('admin.manual.destroy', $article))
            ->assertSessionHasErrors('confirm');

        $this->actingAs($user)
            ->delete(route('admin.manual.destroy', $article), ['confirm' => '1'])
            ->assertRedirect(route('admin.manual.index'));

        $this->assertDatabaseMissing('manual_articles', ['id' => $article->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'manual.article.deleted', 'auditable_id' => $article->id]);
    }

    public function test_category_crud_and_preview_are_available_to_manager(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = $this->userWithRole('Direccion Tecnica');

        $this->actingAs($user)
            ->post(route('admin.manual.categories.store'), [
                'name' => 'QA operativo',
                'slug' => 'qa-operativo',
                'description' => 'Contenido de prueba',
                'sort_order' => 10,
                'active' => '1',
            ])
            ->assertRedirect(route('admin.manual.index'));

        $category = ManualCategory::query()->where('slug', 'qa-operativo')->firstOrFail();
        $article = ManualArticle::query()->create(array_merge($this->articlePayload($category), [
            'active' => false,
            'published_at' => null,
        ]));

        $this->actingAs($user)->get(route('admin.manual.preview', $article))->assertOk()->assertSee('Validar inventario demo');
        $this->actingAs($user)
            ->patch(route('admin.manual.categories.update', $category), [
                'name' => 'QA actualizado',
                'slug' => 'qa-actualizado',
                'description' => 'Contenido actualizado',
                'sort_order' => 20,
                'active' => '1',
            ])
            ->assertRedirect(route('admin.manual.index'));

        $this->assertDatabaseHas('audit_logs', ['action' => 'manual.category.created', 'auditable_id' => $category->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'manual.category.updated', 'auditable_id' => $category->id]);
    }

    /** @return array<string, mixed> */
    private function articlePayload(ManualCategory $category): array
    {
        return [
            'title' => 'Validar inventario demo',
            'slug' => 'validar-inventario-demo',
            'summary' => 'Tutorial para validar el inventario demo.',
            'category_id' => $category->id,
            'level' => 'operativo',
            'body' => 'Contenido operativo sin datos reales.',
            'steps' => "Abrir inventario\nRevisar estado",
            'prerequisites' => 'Tener permiso de inventario',
            'required_fields' => 'Producto',
            'common_errors' => 'Confundir stock total',
            'expected_result' => 'El lote queda revisado.',
            'blocked_action' => 'Escalar si existe alerta.',
            'escalation_role' => 'Almacen',
            'module' => 'Inventario',
            'permission' => 'inventory.view',
            'route_name' => 'inventory.index',
            'estimated_minutes' => 5,
            'roles' => ['Administrador'],
            'keywords' => "inventario\nlote",
            'sort_order' => 10,
            'version' => '1.0',
            'status' => 'disabled',
        ];
    }

    private function category(): ManualCategory
    {
        return ManualCategory::query()->create([
            'name' => 'Inventario QA',
            'slug' => 'inventario-qa',
            'description' => 'Categoria de pruebas',
            'sort_order' => 1,
            'active' => true,
        ]);
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create(['active' => true]);
        $user->assignRole(Role::findByName($roleName));

        return $user;
    }
}
