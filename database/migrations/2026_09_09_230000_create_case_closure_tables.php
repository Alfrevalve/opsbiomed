<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_materials_used', function (Blueprint $table): void {
            $table->foreignId('reservation_id')->nullable()->after('case_id')->constrained('reservations')->nullOnDelete();
            $table->unsignedInteger('reserved_qty')->default(0)->after('inventory_lot_id');
            $table->unsignedInteger('returned_qty')->default(0)->after('unused_opened_qty');
            $table->unsignedInteger('failure_qty')->default(0)->after('returned_qty');
            $table->unsignedInteger('difference_qty')->default(0)->after('failure_qty');
            $table->text('difference_reason')->nullable()->after('difference_qty');
            $table->text('evidence_description')->nullable()->after('evidence_path');
            $table->string('evidence_reference')->nullable()->after('evidence_description');
            $table->text('failure_description')->nullable()->after('evidence_reference');
            $table->decimal('unit_price', 12, 2)->nullable()->after('failure_description');
            $table->decimal('subtotal', 12, 2)->default(0)->after('unit_price');
            $table->boolean('cost_zero')->default(false)->after('subtotal');
            $table->text('cost_zero_reason')->nullable()->after('cost_zero');
            $table->boolean('requires_approval')->default(false)->after('cost_zero_reason');
            $table->index(['case_id', 'reservation_id']);
        });

        Schema::create('product_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('unit_price', 12, 2);
            $table->string('currency', 3)->default('PEN');
            $table->boolean('active')->default(true)->index();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['product_id', 'active', 'valid_from']);
        });

        Schema::create('case_valuations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_id')->unique()->constrained('surgery_cases')->cascadeOnDelete();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('currency', 3)->default('PEN');
            $table->string('status')->default('preliminar')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('case_valuation_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_valuation_id')->constrained('case_valuations')->cascadeOnDelete();
            $table->foreignId('case_id')->constrained('surgery_cases')->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->nullOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_lot_id')->constrained('inventory_lots')->restrictOnDelete();
            $table->unsignedInteger('quantity_used')->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->boolean('cost_zero')->default(false);
            $table->text('cost_zero_reason')->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->timestamps();
            $table->index(['case_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_valuation_lines');
        Schema::dropIfExists('case_valuations');
        Schema::dropIfExists('product_prices');

        Schema::table('case_materials_used', function (Blueprint $table): void {
            $table->dropIndex(['case_id', 'reservation_id']);
            $table->dropForeign(['reservation_id']);
            $table->dropColumn([
                'reservation_id',
                'reserved_qty',
                'returned_qty',
                'failure_qty',
                'difference_qty',
                'difference_reason',
                'evidence_description',
                'evidence_reference',
                'failure_description',
                'unit_price',
                'subtotal',
                'cost_zero',
                'cost_zero_reason',
                'requires_approval',
            ]);
        });
    }
};
