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
        Schema::table('case_returns', function (Blueprint $table): void {
            $table->string('inspection_result')->nullable()->after('condition')->index();
            $table->text('inspection_observations')->nullable()->after('inspection_result');
            $table->string('inspection_evidence_reference')->nullable()->after('inspection_observations');
            $table->foreignId('inspection_responsible_id')->nullable()->after('inspection_evidence_reference')->constrained('users')->nullOnDelete();
            $table->timestamp('inspection_date')->nullable()->after('inspection_responsible_id');
            $table->foreignId('technical_failure_id')->nullable()->after('inspection_date')->constrained('failures')->nullOnDelete();
            $table->text('non_reusable_reason')->nullable()->after('technical_failure_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('case_returns', function (Blueprint $table): void {
            $table->dropForeign(['inspection_responsible_id']);
            $table->dropForeign(['technical_failure_id']);
            $table->dropIndex(['inspection_result']);
            $table->dropColumn([
                'inspection_result',
                'inspection_observations',
                'inspection_evidence_reference',
                'inspection_responsible_id',
                'inspection_date',
                'technical_failure_id',
                'non_reusable_reason',
            ]);
        });
    }
};
