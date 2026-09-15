<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('module', 100);
            $table->string('action', 100);
            $table->string('input_type', 100);
            $table->boolean('contains_personal_data')->default(false);
            $table->boolean('contains_health_data')->default(false);
            $table->string('ai_provider', 100)->nullable();
            $table->boolean('human_review_required')->default(true);
            $table->foreignId('human_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('human_reviewed_at')->nullable();
            $table->string('human_review_outcome', 20)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['module', 'action']);
            $table->index(['user_id', 'created_at']);
            $table->index('human_review_outcome');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
