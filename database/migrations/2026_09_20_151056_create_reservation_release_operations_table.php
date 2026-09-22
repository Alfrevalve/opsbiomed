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
        Schema::create('reservation_release_operations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('idempotency_key')->unique();
            $table->string('operation_type', 40);
            $table->foreignId('case_id')->constrained('surgery_cases')->restrictOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('reason');
            $table->string('status', 20)->default('processing');
            $table->json('result')->nullable();
            $table->timestamps();

            $table->index(['case_id', 'operation_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_release_operations');
    }
};
