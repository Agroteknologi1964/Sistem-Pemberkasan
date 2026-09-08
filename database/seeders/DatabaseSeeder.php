<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Admin ──────────────────────────────────────────────────────────

        $admin = User::create([
            'name'                  => 'Administrator',
            'email'                 => 'admin@agrotek.com',
            'password'              => Hash::make('Agr0t3knologi123'),
            'role'                  => 'admin',
            'is_active'             => true,
            'force_change_password' => false,
        ]);

        $this->command->info('Seeder selesai!');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Admin', 'admin@agrotek.com', 'Agr0t3knologi123'],
            ]
        );
    }
}
