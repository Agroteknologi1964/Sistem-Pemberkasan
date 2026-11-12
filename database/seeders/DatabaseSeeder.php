<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Kredensial admin diambil dari .env agar password produksi tidak
        // pernah ikut tercatat di repository. Fallback hanya untuk lokal.
        $email    = env('SEED_ADMIN_EMAIL', 'admin@agrotek.com');
        $password = env('SEED_ADMIN_PASSWORD');

        if (blank($password)) {
            if (app()->isProduction()) {
                $this->command->error('SEED_ADMIN_PASSWORD belum diisi di .env — seeder admin dilewati.');
                return;
            }

            $password = 'Agr0t3knologi123';
            $this->command->warn('SEED_ADMIN_PASSWORD kosong, memakai password dev: "Agr0t3knologi123"');
        }

        // updateOrCreate membuat seeder aman dijalankan berulang kali
        // (tidak lagi gagal "Duplicate entry" saat db:seed diulang).
        $admin = User::updateOrCreate(
            ['email' => $email],
            [
                'name'                  => 'Administrator',
                'password'              => Hash::make($password),
                'role'                  => 'admin',
                'is_active'             => true,
                'force_change_password' => true,
            ]
        );

        $this->command->info($admin->wasRecentlyCreated ? 'Admin dibuat.' : 'Admin sudah ada, kredensial diperbarui.');
        $this->command->table(
            ['Role', 'Email'],
            [['Admin', $email]]
        );
        $this->command->warn('Password admin diambil dari SEED_ADMIN_PASSWORD di .env.');
        $this->command->warn('Password wajib diganti saat login pertama (force_change_password = true).');
    }
}
