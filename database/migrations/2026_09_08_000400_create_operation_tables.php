<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_materials_sent', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_id')->constrained('surgery_cases')->cascadeOnDelete();
            $table->foreignId('inventory_lot_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('guide_number')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('case_materials_used', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_id')->constrained('surgery_cases')->cascadeOnDelete();
            $table->foreignId('inventory_lot_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('opened_qty')->default(0);
            $table->unsignedInteger('used_qty')->default(0);
            $table->unsignedInteger('unused_opened_qty')->default(0);
            $table->string('evidence_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('case_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_id')->constrained('surgery_cases')->cascadeOnDelete();
            $table->foreignId('inventory_lot_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('returned_qty')->default(0);
            $table->string('condition')->default('pendiente_inspeccion');
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('inspected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('failures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_id')->nullable()->constrained('surgery_cases')->nullOnDelete();
            $table->foreignId('inventory_lot_id')->nullable()->constrained()->nullOnDelete();
            $table->string('severity')->default('media')->index();
            $table->string('status')->default('reportada')->index();
            $table->boolean('preventive_block')->default(false);
            $table->text('description');
            $table->text('action_taken')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_id')->nullable()->constrained('surgery_cases')->nullOnDelete();
            $table->string('type')->index();
            $table->string('status')->default('pendiente')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('evidence')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('billing_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_id')->constrained('surgery_cases')->cascadeOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('PEN');
            $table->string('invoice_status')->default('pendiente')->index();
            $table->string('purchase_order')->nullable();
            $table->string('payment_status')->default('pendiente')->index();
            $table->unsignedInteger('debt_days')->default(0)->index();
            $table->text('no_billing_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('commercial_followups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_id')->nullable()->constrained('surgery_cases')->nullOnDelete();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('opportunity_stage')->default('observacion')->index();
            $table->timestamp('next_action_at')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('auditable_type')->nullable()->index();
            $table->unsignedBigInteger('auditable_id')->nullable()->index();
            $table->string('action')->index();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('commercial_followups');
        Schema::dropIfExists('billing_records');
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('failures');
        Schema::dropIfExists('case_returns');
        Schema::dropIfExists('case_materials_used');
        Schema::dropIfExists('case_materials_sent');
    }
};
