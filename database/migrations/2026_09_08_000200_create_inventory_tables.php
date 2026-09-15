<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('product_line')->nullable();
            $table->string('family')->nullable();
            $table->string('subfamily')->nullable();
            $table->string('product_code')->unique();
            $table->string('normalized_code')->nullable()->index();
            $table->string('name');
            $table->string('regulatory_record')->nullable();
            $table->date('regulatory_expiry')->nullable();
            $table->decimal('length_cm', 5, 2)->nullable()->index();
            $table->decimal('diameter_mm', 5, 2)->nullable()->index();
            $table->string('cut_type')->nullable()->index();
            $table->string('component_type')->nullable()->index();
            $table->string('tracking_type')->default('lot');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('inventory_lots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('lot')->nullable()->index();
            $table->string('serial')->nullable()->index();
            $table->date('expiry')->nullable()->index();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->integer('quantity')->default(0);
            $table->string('status')->default('apto')->index();
            $table->boolean('eligible_flag')->default(true)->index();
            $table->text('observations')->nullable();
            $table->timestamps();
            $table->index(['product_id', 'warehouse_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_lots');
        Schema::dropIfExists('products');
    }
};
