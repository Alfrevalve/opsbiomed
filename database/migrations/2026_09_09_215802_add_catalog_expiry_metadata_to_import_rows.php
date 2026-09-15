<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_import_rows', function (Blueprint $table): void {
            $table->string('classification')->nullable()->after('product_name');
            $table->boolean('expiry_required')->default(true)->after('classification');
            $table->boolean('expiry_is_na')->default(false)->after('expiry_required');
        });

        Schema::table('catalog_imports', function (Blueprint $table): void {
            $table->unsignedInteger('na_accepted')->default(0)->after('warnings');
            $table->unsignedInteger('na_blocked')->default(0)->after('na_accepted');
        });
    }

    public function down(): void
    {
        Schema::table('catalog_import_rows', function (Blueprint $table): void {
            $table->dropColumn(['classification', 'expiry_required', 'expiry_is_na']);
        });

        Schema::table('catalog_imports', function (Blueprint $table): void {
            $table->dropColumn(['na_accepted', 'na_blocked']);
        });
    }
};
