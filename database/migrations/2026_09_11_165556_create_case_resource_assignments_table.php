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
        Schema::create('case_resource_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('surgery_case_id')->constrained('surgery_cases')->cascadeOnDelete();
            $table->foreignId('inventory_lot_id')->nullable()->constrained()->nullOnDelete();
            $table->string('resource_type', 20)->index();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamps();
            $table->index(['surgery_case_id', 'resource_type']);
            $table->index(['inventory_lot_id', 'assigned_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('case_resource_assignments');
    }
};
