<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_prices', function (Blueprint $table): void {
            $table->foreignId('institution_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            $table->foreignId('doctor_id')->nullable()->after('institution_id')->constrained()->nullOnDelete();
            $table->decimal('minimum_price', 12, 2)->nullable()->after('unit_price');
            $table->string('price_type')->default('lista')->after('currency');
            $table->text('observations')->nullable()->after('valid_until');
            $table->index(
                ['product_id', 'institution_id', 'doctor_id', 'active', 'valid_from'],
                'product_prices_lookup_idx',
            );
        });

        Schema::table('case_materials_used', function (Blueprint $table): void {
            $table->decimal('minimum_unit_price', 12, 2)->nullable()->after('unit_price');
            $table->boolean('price_below_minimum')->default(false)->after('minimum_unit_price');
        });

        Schema::table('case_valuation_lines', function (Blueprint $table): void {
            $table->decimal('minimum_unit_price', 12, 2)->nullable()->after('unit_price');
            $table->boolean('price_below_minimum')->default(false)->after('minimum_unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('case_valuation_lines', function (Blueprint $table): void {
            $table->dropColumn(['minimum_unit_price', 'price_below_minimum']);
        });

        Schema::table('case_materials_used', function (Blueprint $table): void {
            $table->dropColumn(['minimum_unit_price', 'price_below_minimum']);
        });

        Schema::table('product_prices', function (Blueprint $table): void {
            $table->dropIndex('product_prices_lookup_idx');
            $table->dropForeign(['institution_id']);
            $table->dropForeign(['doctor_id']);
            $table->dropColumn(['institution_id', 'doctor_id', 'minimum_price', 'price_type', 'observations']);
        });
    }
};
