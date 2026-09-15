<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class InitialUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = (string) env('INITIAL_ADMIN_PASSWORD');

        if ($password === '') {
            throw new \RuntimeException('Define INITIAL_ADMIN_PASSWORD antes de ejecutar InitialUserSeeder.');
        }

        $user = User::updateOrCreate(
            ['email' => 'admin@ops-biomed.local'],
            [
                'name' => 'Administrador OPS BIOMED',
                'password' => $password,
                'email_verified_at' => now(),
                'active' => true,
            ],
        );

        $user->syncRoles('Administrador');
    }
}
