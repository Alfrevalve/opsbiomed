<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Institution;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_user_management_screens(): void
    {
        $administrator = $this->administrator();
        $managedUser = User::factory()->create(['name' => 'Usuario QA', 'email' => 'qa.user@example.test']);
        $managedUser->assignRole(Role::findByName('Comercial'));

        $this->actingAs($administrator)->get(route('admin.users.index'))->assertOk()->assertSee('Usuarios y accesos')->assertSee($managedUser->email);
        $this->actingAs($administrator)->get(route('admin.users.create'))->assertOk()->assertSee('Crear usuario');
        $this->actingAs($administrator)->get(route('admin.users.show', $managedUser))->assertOk()->assertSee('Roles y permisos');
        $this->actingAs($administrator)->get(route('admin.users.edit', $managedUser))->assertOk()->assertSee('Editar usuario')->assertSee('Restablecer password');
    }

    public function test_user_without_manage_permission_cannot_access_user_management(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('Comercial'));

        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_user_without_manage_permission_cannot_create_managed_users(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('Comercial'));

        $this->actingAs($user)
            ->post(route('admin.users.store'), [
                'name' => 'Usuario no autorizado',
                'email' => 'unauthorized@example.test',
                'password' => 'StrongPassword2026!',
                'password_confirmation' => 'StrongPassword2026!',
                'role' => 'Comercial',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'unauthorized@example.test']);
    }

    public function test_admin_can_create_user_with_role_and_profile(): void
    {
        $administrator = $this->administrator();
        $institution = Institution::create(['name' => 'Institucion usuarios '.uniqid(), 'active' => true]);

        $response = $this->actingAs($administrator)->post(route('admin.users.store'), [
            'name' => 'Almacen QA',
            'email' => 'almacen.qa@example.test',
            'password' => 'Temporal2026!',
            'password_confirmation' => 'Temporal2026!',
            'role' => 'Almacen',
            'active' => 1,
            'job_title' => 'Almacen',
            'area' => 'Logistica',
            'phone' => '999888777',
            'institution_id' => $institution->id,
        ]);

        $user = User::query()->where('email', 'almacen.qa@example.test')->firstOrFail();

        $response->assertRedirect(route('admin.users.show', $user));
        $this->assertTrue($user->hasRole('Almacen'));
        $this->assertTrue(Hash::check('Temporal2026!', $user->password));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'job_title' => 'Almacen',
            'area' => 'Logistica',
            'phone' => '999888777',
            'institution_id' => $institution->id,
            'active' => 1,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.created', 'auditable_id' => $user->id]);
    }

    public function test_email_must_be_unique_and_password_must_have_minimum_length(): void
    {
        $administrator = $this->administrator();
        User::factory()->create(['email' => 'duplicate@example.test']);

        $response = $this->actingAs($administrator)->post(route('admin.users.store'), [
            'name' => 'Duplicado',
            'email' => 'duplicate@example.test',
            'password' => 'short',
            'password_confirmation' => 'short',
            'role' => 'Comercial',
        ]);

        $response->assertSessionHasErrors(['email', 'password']);
    }

    public function test_admin_can_update_role_profile_and_status_with_audit(): void
    {
        $administrator = $this->administrator();
        $managedUser = User::factory()->create(['email' => 'update.user@example.test']);
        $managedUser->assignRole(Role::findByName('Programador Quirurgico'));

        $response = $this->actingAs($administrator)->patch(route('admin.users.update', $managedUser), [
            'name' => 'Usuario actualizado',
            'email' => 'updated.user@example.test',
            'role' => 'Jefe de Linea',
            'active' => 0,
            'job_title' => 'Jefe de Linea',
            'area' => 'Operaciones',
            'phone' => '987654321',
            'institution_id' => '',
        ]);

        $managedUser->refresh();

        $response->assertRedirect(route('admin.users.show', $managedUser));
        $this->assertSame('Usuario actualizado', $managedUser->name);
        $this->assertFalse($managedUser->active);
        $this->assertTrue($managedUser->hasRole('Jefe de Linea'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.updated', 'auditable_id' => $managedUser->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.role_changed', 'auditable_id' => $managedUser->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.disabled', 'auditable_id' => $managedUser->id]);
    }

    public function test_admin_can_disable_and_enable_user_with_audit(): void
    {
        $administrator = $this->administrator();
        $managedUser = User::factory()->create();
        $managedUser->assignRole(Role::findByName('Comercial'));

        $this->actingAs($administrator)->post(route('admin.users.disable', $managedUser))->assertRedirect(route('admin.users.show', $managedUser));
        $this->assertDatabaseHas('users', ['id' => $managedUser->id, 'active' => 0]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.disabled', 'auditable_id' => $managedUser->id]);

        $this->actingAs($administrator)->post(route('admin.users.enable', $managedUser))->assertRedirect(route('admin.users.show', $managedUser));
        $this->assertDatabaseHas('users', ['id' => $managedUser->id, 'active' => 1]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.enabled', 'auditable_id' => $managedUser->id]);
    }

    public function test_inactive_user_cannot_operate(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $inactiveUser = User::factory()->create(['active' => false]);
        $inactiveUser->assignRole(Role::findByName('Comercial'));

        $this->actingAs($inactiveUser)->get(route('dashboard.ops'))->assertForbidden();
    }

    public function test_user_cannot_disable_themselves_or_remove_last_admin_role(): void
    {
        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->from(route('admin.users.show', $administrator))
            ->post(route('admin.users.disable', $administrator))
            ->assertRedirect(route('admin.users.show', $administrator))
            ->assertSessionHasErrors('user');

        $this->actingAs($administrator)
            ->from(route('admin.users.show', $administrator))
            ->patch(route('admin.users.update', $administrator), [
                'name' => $administrator->name,
                'email' => $administrator->email,
                'role' => 'Comercial',
                'active' => 1,
            ])
            ->assertRedirect(route('admin.users.show', $administrator))
            ->assertSessionHasErrors('user');

        $administrator->refresh();
        $this->assertTrue($administrator->hasRole('Administrador'));
        $this->assertTrue($administrator->active);
    }

    public function test_last_active_admin_cannot_be_disabled_by_another_manager(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $permission = Permission::findOrCreate('users.manage');
        $managerRole = Role::findOrCreate('QA User Manager');
        $managerRole->givePermissionTo($permission);
        $manager = User::factory()->create();
        $manager->assignRole($managerRole);
        $administrator = User::factory()->create(['active' => true]);
        $administrator->assignRole(Role::findByName('Administrador'));

        $this->actingAs($manager)
            ->from(route('admin.users.show', $administrator))
            ->post(route('admin.users.disable', $administrator))
            ->assertRedirect(route('admin.users.show', $administrator))
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', ['id' => $administrator->id, 'active' => 1]);
    }

    public function test_admin_can_reset_password_and_audit_action(): void
    {
        $administrator = $this->administrator();
        $managedUser = User::factory()->create(['password' => 'old-password']);
        $managedUser->assignRole(Role::findByName('Comercial'));

        $this->actingAs($administrator)
            ->post(route('admin.users.reset-password', $managedUser), [
                'password' => 'NewPassword2026!',
                'password_confirmation' => 'NewPassword2026!',
            ])
            ->assertRedirect(route('admin.users.show', $managedUser));

        $managedUser->refresh();
        $this->assertTrue(Hash::check('NewPassword2026!', $managedUser->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.password_reset', 'auditable_id' => $managedUser->id]);
    }

    public function test_user_detail_renders_related_audit_history(): void
    {
        $administrator = $this->administrator();
        $managedUser = User::factory()->create();
        $managedUser->assignRole(Role::findByName('Comercial'));
        AuditLog::create([
            'user_id' => $administrator->id,
            'auditable_type' => User::class,
            'auditable_id' => $managedUser->id,
            'action' => 'user.updated',
            'before' => ['active' => true],
            'after' => ['active' => false],
            'created_at' => now(),
        ]);

        $this->actingAs($administrator)
            ->get(route('admin.users.show', $managedUser))
            ->assertOk()
            ->assertSee('Historial de auditoria')
            ->assertSee('user.updated');
    }

    private function administrator(): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $administrator = User::factory()->create(['active' => true]);
        $administrator->assignRole(Role::findByName('Administrador'));

        return $administrator;
    }
}
