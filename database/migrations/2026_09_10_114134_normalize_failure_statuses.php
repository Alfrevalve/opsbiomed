<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('failures')
            ->whereIn('status', ['abierta', 'open'])
            ->update(['status' => 'reportada']);

        DB::table('failures')
            ->whereIn('status', ['resuelta', 'resolved'])
            ->where(function ($query): void {
                $query->whereNotNull('released_at')
                    ->orWhereNotNull('released_by')
                    ->orWhereNotNull('release_notes');
            })
            ->update(['status' => 'liberada']);

        DB::table('failures')
            ->whereIn('status', ['resuelta', 'resolved', 'cerrado'])
            ->update(['status' => 'cerrada']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Status normalization is intentionally irreversible.
    }
};
