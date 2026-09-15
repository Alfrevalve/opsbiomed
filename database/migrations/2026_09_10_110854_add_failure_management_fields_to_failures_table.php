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
        Schema::table('failures', function (Blueprint $table): void {
            $table->foreignId('product_id')->nullable()->after('inventory_lot_id')->constrained()->nullOnDelete();
            $table->string('failure_type')->nullable()->after('severity')->index();
            $table->string('occurrence_moment')->nullable()->after('failure_type')->index();
            $table->string('evidence_reference')->nullable()->after('action_taken');
            $table->foreignId('responsible_technical_id')->nullable()->after('reported_by')->constrained('users')->nullOnDelete();
            $table->text('diagnosis')->nullable()->after('responsible_technical_id');
            $table->text('probable_cause')->nullable()->after('diagnosis');
            $table->text('corrective_action')->nullable()->after('probable_cause');
            $table->boolean('requires_supplier')->default(false)->after('corrective_action');
            $table->boolean('requires_replacement')->default(false)->after('requires_supplier');
            $table->timestamp('reviewed_at')->nullable()->after('requires_replacement');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable()->after('reviewed_by');
            $table->foreignId('released_by')->nullable()->after('released_at')->constrained('users')->nullOnDelete();
            $table->text('release_notes')->nullable()->after('released_by');
            $table->timestamp('retired_at')->nullable()->after('release_notes');
            $table->foreignId('retired_by')->nullable()->after('retired_at')->constrained('users')->nullOnDelete();
            $table->text('retirement_reason')->nullable()->after('retired_by');
            $table->text('retirement_evidence')->nullable()->after('retirement_reason');
            $table->index(['status', 'severity']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('failures', function (Blueprint $table): void {
            $table->dropIndex(['status', 'severity']);
            $table->dropForeign(['product_id']);
            $table->dropForeign(['responsible_technical_id']);
            $table->dropForeign(['reviewed_by']);
            $table->dropForeign(['released_by']);
            $table->dropForeign(['retired_by']);
            $table->dropColumn([
                'product_id',
                'failure_type',
                'occurrence_moment',
                'evidence_reference',
                'responsible_technical_id',
                'diagnosis',
                'probable_cause',
                'corrective_action',
                'requires_supplier',
                'requires_replacement',
                'reviewed_at',
                'reviewed_by',
                'released_at',
                'released_by',
                'release_notes',
                'retired_at',
                'retired_by',
                'retirement_reason',
                'retirement_evidence',
            ]);
        });
    }
};
