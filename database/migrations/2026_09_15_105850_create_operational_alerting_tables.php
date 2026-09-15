<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('operational_alerting_tables');
        Schema::dropIfExists('operational_sla_alerts_and_notification_logs_tables');

        Schema::create('operational_slas', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name');
            $table->string('module', 80);
            $table->string('trigger_event', 120)->nullable();
            $table->unsignedInteger('target_minutes')->default(0);
            $table->string('time_unit', 20)->default('minutes');
            $table->string('priority', 20)->default('medium');
            $table->json('responsible_roles')->nullable();
            $table->json('schedule_rules')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('operational_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('operational_sla_id')->constrained('operational_slas')->cascadeOnDelete();
            $table->nullableMorphs('alertable');
            $table->string('alert_code', 140)->index();
            $table->string('module', 80)->index();
            $table->string('title');
            $table->text('description');
            $table->string('priority', 20)->index();
            $table->string('status', 20)->default('open')->index();
            $table->string('responsible_role', 100)->nullable()->index();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('detected_at')->index();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['operational_sla_id', 'alertable_type', 'alertable_id'], 'operational_alerts_sla_entity_idx');
        });

        Schema::create('notification_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('operational_alert_id')->nullable()->constrained('operational_alerts')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 30);
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('subject')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['operational_alert_id', 'channel'], 'notification_logs_alert_channel_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('operational_alerts');
        Schema::dropIfExists('operational_slas');
    }
};
