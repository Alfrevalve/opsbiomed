<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_imports', function (Blueprint $table): void {
            $table->unsignedInteger('inconsistencies')->default(0)->after('warnings');
        });

        Schema::table('catalog_import_rows', function (Blueprint $table): void {
            $table->json('inconsistencies')->nullable()->after('warnings');
        });

        Schema::create('inventory_import_issues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('catalog_import_id')->constrained('catalog_imports')->cascadeOnDelete();
            $table->foreignId('catalog_import_row_id')->constrained('catalog_import_rows')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('product_code')->nullable()->index();
            $table->string('product_name')->nullable();
            $table->string('lot')->nullable()->index();
            $table->string('serial')->nullable();
            $table->string('warehouse_key')->index();
            $table->string('warehouse_name');
            $table->integer('quantity');
            $table->string('issue_type')->default('negative_quantity')->index();
            $table->text('message');
            $table->string('status')->default('pending')->index();
            $table->timestamps();
            $table->index(['catalog_import_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_import_issues');

        Schema::table('catalog_import_rows', function (Blueprint $table): void {
            $table->dropColumn('inconsistencies');
        });

        Schema::table('catalog_imports', function (Blueprint $table): void {
            $table->dropColumn('inconsistencies');
        });
    }
};
