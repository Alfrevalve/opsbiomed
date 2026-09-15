<?php

namespace Database\Seeders;

use App\Services\Operations\OperationalSlaService;
use Illuminate\Database\Seeder;

class OperationalSlaSeeder extends Seeder
{
    public function run(): void
    {
        app(OperationalSlaService::class)->ensureDefaults();
    }
}
