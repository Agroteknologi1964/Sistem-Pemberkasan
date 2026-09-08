<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminCommand extends Command
{
    protected $signature   = 'admin:create {--name=Administrator} {--email=admin@pemberkasan.test} {--password=}';
    protected $description = 'Buat akun admin baru';

    public function handle(): int
    {
        $name  = $this->option('name');
        $email = $this->option('email');

        if (User::where('email', $email)->exists()) {
            $this->error("User dengan email {$email} sudah ada.");
            return Command::FAILURE;
        }

        $password = $this->option('password') ?? $this->secret('Password admin (min. 8 karakter)');

        if (strlen($password) < 8) {
            $this->error('Password minimal 8 karakter.');
            return Command::FAILURE;
        }

        User::create([
            'name'                  => $name,
            'email'                 => $email,
            'password'              => Hash::make($password),
            'role'                  => 'admin',
            'is_active'             => true,
            'force_change_password' => false,
        ]);

        $this->info("✓ Admin berhasil dibuat:");
        $this->table(['Field', 'Value'], [
            ['Name',  $name],
            ['Email', $email],
            ['Role',  'admin'],
        ]);

        return Command::SUCCESS;
    }
}
