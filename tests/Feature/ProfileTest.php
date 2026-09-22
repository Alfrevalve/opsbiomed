<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk()
            ->assertSee('Mi perfil')
            ->assertSee('Datos de cuenta, seguridad y acceso operativo.')
            ->assertSee('OPS BIOMED puede usar funciones asistidas por IA únicamente como apoyo operativo.')
            ->assertSee('Sistema de apoyo operativo. No reemplaza criterio clínico ni técnico.')
            ->assertSee('No registrado')
            ->assertDontSee('name="role"', false)
            ->assertDontSee('name="active"', false);
    }

    public function test_profile_shows_operational_summary_and_effective_permissions(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $institution = Institution::create([
            'name' => 'Clinica Perfil QA',
            'active' => true,
        ]);
        $user = User::factory()->create([
            'name' => 'Usuario Perfil QA',
            'area' => 'Operaciones',
            'job_title' => 'Jefe de Linea',
            'phone' => '999888777',
            'institution_id' => $institution->id,
        ]);
        $user->assignRole(Role::findByName('Jefe de Linea'));

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk()
            ->assertSee('Usuario Perfil QA')
            ->assertSee('Jefe de Linea')
            ->assertSee('Operaciones')
            ->assertSee('999888777')
            ->assertSee('Clinica Perfil QA')
            ->assertSee('Ver solicitudes')
            ->assertSee('Gestionar maestros')
            ->assertDontSee('name="role"', false)
            ->assertDontSee('name="active"', false);
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_non_admin_cannot_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response->assertForbidden();

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh());
    }

    public function test_profile_delete_endpoint_is_not_a_user_management_action(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response->assertForbidden();

        $this->assertNotNull($user->fresh());
    }
}
