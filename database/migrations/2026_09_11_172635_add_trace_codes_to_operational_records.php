<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inventory_lots', function (Blueprint $table): void {
            $table->string('trace_code', 160)->nullable()->unique();
        });

        Schema::table('surgery_cases', function (Blueprint $table): void {
            $table->string('trace_code', 160)->nullable()->unique();
        });

        Schema::table('reservations', function (Blueprint $table): void {
            $table->string('trace_code', 160)->nullable()->unique();
        });

        Schema::table('case_returns', function (Blueprint $table): void {
            $table->string('trace_code', 160)->nullable()->unique();
        });

        Schema::table('failures', function (Blueprint $table): void {
            $table->string('trace_code', 160)->nullable()->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('failures', function (Blueprint $table): void {
            $table->dropUnique(['trace_code']);
            $table->dropColumn('trace_code');
        });

        Schema::table('case_returns', function (Blueprint $table): void {
            $table->dropUnique(['trace_code']);
            $table->dropColumn('trace_code');
        });

        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropUnique(['trace_code']);
            $table->dropColumn('trace_code');
        });

        Schema::table('surgery_cases', function (Blueprint $table): void {
            $table->dropUnique(['trace_code']);
            $table->dropColumn('trace_code');
        });

        Schema::table('inventory_lots', function (Blueprint $table): void {
            $table->dropUnique(['trace_code']);
            $table->dropColumn('trace_code');
        });
    }
};
