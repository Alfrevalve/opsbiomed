<?php

namespace Tests\Feature;

use App\Models\AiUsageLog;
use App\Models\User;
use App\Services\Compliance\AiUsageLogger;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ComplianceAiPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_view_the_ai_policy(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('Almacen'));

        $this->actingAs($user)
            ->get(route('compliance.ai-policy'))
            ->assertOk()
            ->assertSee('Politica de uso de IA')
            ->assertSee('11 de septiembre de 2026')
            ->assertSee('Sugerencia asistida')
            ->assertSee('No se almacenan prompts ni respuestas con datos sensibles.');
    }

    public function test_user_without_compliance_permission_cannot_view_the_ai_policy(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('compliance.ai-policy'))
            ->assertForbidden();
    }

    public function test_compliance_permissions_are_seeded_with_governance_boundaries(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $this->assertTrue(Role::findByName('Administrador')->hasPermissionTo('compliance.manage'));
        $this->assertTrue(Role::findByName('Gerencia')->hasPermissionTo('compliance.manage'));
        $this->assertTrue(Role::findByName('Almacen')->hasPermissionTo('compliance.view'));
        $this->assertFalse(Role::findByName('Almacen')->hasPermissionTo('compliance.manage'));
    }

    public function test_usage_log_records_only_metadata_and_human_review(): void
    {
        $user = User::factory()->create();
        $logger = app(AiUsageLogger::class);

        $log = $logger->record(
            module: 'inventory',
            action: 'forecast_suggestion',
            inputType: 'aggregated_stock',
            aiProvider: 'future_provider',
            user: $user,
        );

        $reviewedLog = $logger->recordHumanReview($log, 'corregida', $user);

        $this->assertModelExists($reviewedLog);
        $this->assertSame($user->id, $reviewedLog->user_id);
        $this->assertSame('inventory', $reviewedLog->module);
        $this->assertSame('forecast_suggestion', $reviewedLog->action);
        $this->assertFalse($reviewedLog->contains_personal_data);
        $this->assertFalse($reviewedLog->contains_health_data);
        $this->assertTrue($reviewedLog->human_review_required);
        $this->assertSame($user->id, $reviewedLog->human_reviewed_by);
        $this->assertSame('corregida', $reviewedLog->human_review_outcome);
        $this->assertNotNull($reviewedLog->human_reviewed_at);
        $this->assertFalse(Schema::hasColumn('ai_usage_logs', 'prompt'));
        $this->assertFalse(Schema::hasColumn('ai_usage_logs', 'output'));
        $this->assertInstanceOf(AiUsageLog::class, $reviewedLog);
    }
}
