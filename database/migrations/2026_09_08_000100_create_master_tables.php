<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutions', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('ruc', 20)->nullable()->index();
            $table->string('billing_policy')->nullable();
            $table->string('debt_status')->default('sin_revision')->index();
            $table->string('agreement_type')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('doctors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('commercial_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('specialty')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['name', 'institution_id']);
        });

        Schema::create('patients', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('full_name')->nullable();
            $table->text('document_number')->nullable();
            $table->text('phone')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('surgery_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name')->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('type')->index();
            $table->unsignedInteger('lead_time_hours')->default(0);
            $table->boolean('counts_as_immediate')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('surgery_types');
        Schema::dropIfExists('patients');
        Schema::dropIfExists('doctors');
        Schema::dropIfExists('institutions');
    }
};
