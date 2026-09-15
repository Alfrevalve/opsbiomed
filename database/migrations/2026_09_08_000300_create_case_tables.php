<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kit_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('surgery_type_id')->constrained()->cascadeOnDelete();
            $table->decimal('length_cm', 5, 2)->nullable();
            $table->decimal('diameter_mm', 5, 2)->nullable();
            $table->string('cut_type');
            $table->string('component_type')->default('fresa');
            $table->unsignedInteger('min_qty')->default(3);
            $table->unsignedInteger('target_qty')->default(5);
            $table->string('criticality')->default('critica');
            $table->boolean('required')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['surgery_type_id', 'length_cm', 'diameter_mm', 'cut_type', 'component_type'], 'kit_rule_combo_idx');
        });

        Schema::create('surgery_cases', function (Blueprint $table): void {
            $table->id();
            $table->string('case_code')->unique();
            $table->string('status')->default('solicitado')->index();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('surgery_type_id')->constrained()->restrictOnDelete();
            $table->timestamp('scheduled_at')->index();
            $table->string('priority')->default('normal')->index();
            $table->string('procedure_name');
            $table->string('request_origin')->index();
            $table->string('commercial_condition')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_id')->constrained('surgery_cases')->cascadeOnDelete();
            $table->foreignId('inventory_lot_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('status')->default('active')->index();
            $table->foreignId('reserved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
            $table->index(['case_id', 'inventory_lot_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('surgery_cases');
        Schema::dropIfExists('kit_rules');
    }
};
