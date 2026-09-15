<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('job_title')->nullable()->after('last_login_at');
            $table->string('area')->nullable()->after('job_title');
            $table->string('phone', 50)->nullable()->after('area');
            $table->foreignId('institution_id')->nullable()->after('phone')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['institution_id']);
            $table->dropColumn(['job_title', 'area', 'phone', 'institution_id']);
        });
    }
};
