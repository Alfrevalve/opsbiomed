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
        Schema::table('surgery_cases', function (Blueprint $table): void {
            $table->foreignId('assigned_instrumentist_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->index(['scheduled_at', 'assigned_instrumentist_id'], 'surgery_cases_schedule_instrumentist_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('surgery_cases', function (Blueprint $table): void {
            $table->dropIndex('surgery_cases_schedule_instrumentist_idx');
            $table->dropForeign(['assigned_instrumentist_id']);
            $table->dropForeign(['assigned_by']);
            $table->dropColumn([
                'assigned_instrumentist_id',
                'assigned_by',
                'assigned_at',
            ]);
        });
    }
};
