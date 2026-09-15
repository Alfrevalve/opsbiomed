<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_records', function (Blueprint $table): void {
            $table->string('invoice_number')->nullable()->after('purchase_order');
            $table->date('invoice_date')->nullable()->after('invoice_number');
            $table->date('due_date')->nullable()->after('invoice_date');
            $table->decimal('amount_paid', 12, 2)->default(0)->after('amount');
            $table->text('observations')->nullable()->after('no_billing_reason');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()->after('observations');
            $table->index(['payment_status', 'due_date']);
        });

        Schema::table('approvals', function (Blueprint $table): void {
            $table->foreignId('valuation_id')->nullable()->after('case_id')->constrained('case_valuations')->nullOnDelete();
            $table->index(['type', 'valuation_id', 'status']);
        });

        Schema::create('case_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_id')->unique()->constrained('surgery_cases')->cascadeOnDelete();
            $table->string('status')->default('pendiente')->index();
            $table->unsignedInteger('total_reserved')->default(0);
            $table->unsignedInteger('total_used')->default(0);
            $table->unsignedInteger('total_returned')->default(0);
            $table->unsignedInteger('total_unused_opened')->default(0);
            $table->unsignedInteger('total_failure')->default(0);
            $table->unsignedInteger('total_difference')->default(0);
            $table->text('observations')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_reconciliations');

        Schema::table('approvals', function (Blueprint $table): void {
            $table->dropIndex(['type', 'valuation_id', 'status']);
            $table->dropForeign(['valuation_id']);
            $table->dropColumn('valuation_id');
        });

        Schema::table('billing_records', function (Blueprint $table): void {
            $table->dropIndex(['payment_status', 'due_date']);
            $table->dropForeign(['updated_by']);
            $table->dropColumn([
                'invoice_number',
                'invoice_date',
                'due_date',
                'amount_paid',
                'observations',
                'updated_by',
            ]);
        });
    }
};
