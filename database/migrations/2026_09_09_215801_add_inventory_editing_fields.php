<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('classification')->default('consumible')->after('component_type')->index();
            $table->boolean('expiry_required')->default(true)->after('classification');
        });

        Schema::table('inventory_lots', function (Blueprint $table): void {
            $table->string('location')->nullable()->after('warehouse_id');
            $table->string('block_reason')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_lots', function (Blueprint $table): void {
            $table->dropColumn(['location', 'block_reason']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['classification']);
            $table->dropColumn(['classification', 'expiry_required']);
        });
    }
};
