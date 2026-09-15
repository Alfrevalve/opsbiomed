<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_imports', function (Blueprint $table): void {
            $table->id();
            $table->string('original_filename');
            $table->string('disk')->default('private');
            $table->string('stored_path');
            $table->string('checksum', 64)->nullable()->index();
            $table->string('status')->default('validated')->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('products_detected')->default(0);
            $table->unsignedInteger('lots_detected')->default(0);
            $table->unsignedInteger('critical_errors')->default(0);
            $table->unsignedInteger('warnings')->default(0);
            $table->json('stock_by_warehouse')->nullable();
            $table->json('error_summary')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->timestamp('replaced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('catalog_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('catalog_import_id')->constrained('catalog_imports')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('raw_data');
            $table->string('product_line')->nullable();
            $table->string('family')->nullable();
            $table->string('subfamily')->nullable();
            $table->string('product_code')->nullable()->index();
            $table->string('product_name')->nullable();
            $table->string('regulatory_record')->nullable();
            $table->date('regulatory_expiry')->nullable();
            $table->string('lot')->nullable()->index();
            $table->string('serial')->nullable()->index();
            $table->date('detail_expiry')->nullable();
            $table->string('location')->nullable();
            $table->json('warehouse_quantities')->nullable();
            $table->integer('total_quantity')->nullable();
            $table->string('status')->default('valid')->index();
            $table->json('errors')->nullable();
            $table->json('warnings')->nullable();
            $table->timestamps();
            $table->index(['catalog_import_id', 'row_number']);
        });

        Schema::table('inventory_lots', function (Blueprint $table): void {
            $table->foreignId('catalog_import_id')
                ->nullable()
                ->after('warehouse_id')
                ->constrained('catalog_imports')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_lots', function (Blueprint $table): void {
            $table->dropForeign(['catalog_import_id']);
            $table->dropColumn('catalog_import_id');
        });

        Schema::dropIfExists('catalog_import_rows');
        Schema::dropIfExists('catalog_imports');
    }
};
