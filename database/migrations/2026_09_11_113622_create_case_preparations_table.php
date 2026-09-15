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
        Schema::create('case_preparations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_id')->constrained('surgery_cases')->cascadeOnDelete();
            $table->boolean('institution_confirmed')->default(false);
            $table->boolean('doctor_confirmed')->default(false);
            $table->boolean('schedule_confirmed')->default(false);
            $table->boolean('material_confirmed')->default(false);
            $table->boolean('documents_confirmed')->default(false);
            $table->string('guide_number', 100)->nullable();
            $table->string('delivery_evidence_reference', 500)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('prepared_at')->nullable();
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamps();

            $table->unique('case_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('case_preparations');
    }
};
