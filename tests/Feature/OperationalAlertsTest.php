<?php

namespace Tests\Feature;

use App\Models\DocumentEvidence;
use App\Models\NotificationLog;
use App\Models\OperationalAlert;
use App\Models\OperationalSla;
use App\Models\User;
use App\Services\Operations\NotificationDispatchService;
use App\Services\Operations\OperationalAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OperationalAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluator_creates_an_alert_once_and_dispatches_internal_notification(): void
    {
        $responsible = $this->userWithPermissions(['alerts.view'], 'Jefe de Linea');
        DocumentEvidence::create([
            'documentable_type' => 'App\\Models\\SurgeryCase',
            'documentable_id' => 999,
            'document_type' => 'hoja_consumo',
            'title' => 'Hoja pendiente de validacion',
            'status' => 'cargado',
        ]);

        $first = app(OperationalAlertService::class)->evaluate(now());
        $second = app(OperationalAlertService::class)->evaluate(now());

        $this->assertSame(8, $first['slas']);
        $this->assertSame(1, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertDatabaseCount('operational_alerts', 1);
        $this->assertDatabaseHas('operational_alerts', [
            'alert_code' => 'document_pending_validation:App\\Models\\DocumentEvidence:1',
            'responsible_user_id' => $responsible->id,
            'status' => 'open',
        ]);
        $this->assertDatabaseHas('notification_logs', [
            'channel' => 'database',
            'status' => 'sent',
            'user_id' => $responsible->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'alert.created']);
        $this->assertSame(1, NotificationLog::query()->count());
    }

    public function test_expired_alert_is_marked_without_creating_a_duplicate(): void
    {
        $alert = $this->alert();
        $alert->update(['due_at' => now()->subMinute()]);

        $summary = app(OperationalAlertService::class)->evaluate(now());

        $this->assertSame(0, $summary['created']);
        $this->assertSame(1, $summary['expired']);
        $this->assertDatabaseHas('operational_alerts', [
            'id' => $alert->id,
            'status' => 'expired',
        ]);

        $this->assertSame(0, app(OperationalAlertService::class)->evaluate(now())['created']);
    }

    public function test_alerts_require_view_permission_and_authorized_user_can_update_status(): void
    {
        $viewer = $this->userWithPermissions(['alerts.view']);
        $manager = $this->userWithPermissions(['alerts.view', 'alerts.manage', 'alerts.resolve']);
        $alert = $this->alert();

        $this->actingAs($viewer)
            ->get(route('alerts.show', $alert))
            ->assertOk()
            ->assertSee($alert->title);

        $this->actingAs(User::factory()->create())
            ->get(route('alerts.index'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->post(route('alerts.acknowledge', $alert), ['note' => 'Responsable notificado.'])
            ->assertRedirect(route('alerts.show', $alert));

        $this->assertDatabaseHas('operational_alerts', [
            'id' => $alert->id,
            'status' => 'acknowledged',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'alert.acknowledged']);

        $this->actingAs($manager)
            ->post(route('alerts.resolve', $alert), ['note' => 'Pendiente corregido.'])
            ->assertRedirect(route('alerts.show', $alert));

        $this->assertDatabaseHas('operational_alerts', [
            'id' => $alert->id,
            'status' => 'resolved',
            'resolved_by' => $manager->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'alert.resolved']);
    }

    public function test_dashboard_displays_open_sla_alerts_for_authorized_users(): void
    {
        $user = $this->userWithPermissions(['dashboard.view', 'cases.view', 'alerts.view']);
        $alert = $this->alert();

        $this->actingAs($user)
            ->get(route('dashboard.ops'))
            ->assertOk()
            ->assertSee('Alertas SLA')
            ->assertSee('Alertas abiertas')
            ->assertSee('>1<', false);
    }

    public function test_internal_notification_does_not_persist_sensitive_alert_text(): void
    {
        $responsible = $this->userWithPermissions(['alerts.view'], 'Jefe de Linea');
        $alert = $this->alert();
        $alert->update([
            'responsible_user_id' => $responsible->id,
            'title' => 'Paciente Juan Perez - cirugía cervical',
            'description' => 'Documento con datos clínicos identificables.',
        ]);

        $log = app(NotificationDispatchService::class)->dispatchInternal($alert);

        $this->assertNotNull($log);
        $this->assertArrayNotHasKey('title', $log->payload);
        $this->assertStringNotContainsString('Paciente', $log->subject);
        $this->assertStringNotContainsString('Juan Perez', json_encode($log->payload, JSON_THROW_ON_ERROR));
    }

    public function test_evaluation_commands_are_available_with_legacy_alias(): void
    {
        $this->artisan('ops:alerts:evaluate')->assertExitCode(0);
        $this->artisan('app:evaluate-operational-alerts')->assertExitCode(0);

        $this->assertSame(8, OperationalSla::query()->where('active', true)->count());
    }

    private function alert(): OperationalAlert
    {
        $sla = OperationalSla::create([
            'code' => 'test-sla-'.uniqid(),
            'name' => 'SLA de prueba',
            'module' => 'Pruebas',
            'target_minutes' => 60,
            'time_unit' => 'minutes',
            'priority' => 'medium',
            'responsible_roles' => ['Jefe de Linea'],
            'active' => true,
        ]);

        return OperationalAlert::create([
            'operational_sla_id' => $sla->id,
            'alertable_type' => DocumentEvidence::class,
            'alertable_id' => 1,
            'alert_code' => $sla->code.':document:1',
            'module' => 'Pruebas',
            'title' => 'Alerta SLA de prueba',
            'description' => 'Alerta operativa para pruebas.',
            'priority' => 'medium',
            'status' => 'open',
            'due_at' => now()->addHour(),
            'detected_at' => now(),
        ]);
    }

    /** @param list<string> $permissions */
    private function userWithPermissions(array $permissions, ?string $roleName = null): User
    {
        $role = Role::findOrCreate($roleName ?? 'alerts-test-'.uniqid());
        $role->syncPermissions(collect($permissions)->map(fn (string $permission): Permission => Permission::findOrCreate($permission)));

        $user = User::factory()->create(['active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
