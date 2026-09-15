<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table): void {
            $table->string('institution_type')->default('otro')->after('ruc');
            $table->string('address')->nullable()->after('institution_type');
            $table->string('sop_contact')->nullable()->after('address');
            $table->string('pharmacy_contact')->nullable()->after('sop_contact');
            $table->string('billing_contact')->nullable()->after('pharmacy_contact');
            $table->string('phone')->nullable()->after('billing_contact');
            $table->string('email')->nullable()->after('phone');
            $table->text('observations')->nullable()->after('email');
        });

        Schema::table('doctors', function (Blueprint $table): void {
            $table->string('cmp', 30)->nullable()->after('name');
            $table->string('phone')->nullable()->after('commercial_owner_id');
            $table->string('email')->nullable()->after('phone');
            $table->string('commercial_profile')->default('nuevo')->after('email');
            $table->string('potential')->default('medio')->after('commercial_profile');
            $table->text('observations')->nullable()->after('potential');
            $table->index('cmp');
        });

        DB::table('institutions')
            ->where('debt_status', 'sin_revision')
            ->update(['debt_status' => 'al_dia']);
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table): void {
            $table->dropIndex('doctors_cmp_index');
            $table->dropColumn(['cmp', 'phone', 'email', 'commercial_profile', 'potential', 'observations']);
        });

        Schema::table('institutions', function (Blueprint $table): void {
            $table->dropColumn([
                'institution_type',
                'address',
                'sop_contact',
                'pharmacy_contact',
                'billing_contact',
                'phone',
                'email',
                'observations',
            ]);
        });
    }
};
