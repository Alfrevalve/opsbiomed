<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('operational_sla_alerts_and_notification_logs_tables');
    }

    public function down(): void {}
};
