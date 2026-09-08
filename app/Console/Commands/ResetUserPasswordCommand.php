<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ResetUserPasswordCommand extends Command
{
    protected $signature   = 'user:reset-password {email}';
    protected $description = 'Reset password user dan tampilkan password sementara (digunakan admin)';

    public function handle(): int
    {
        $email = $this->argument('email');
        $user  = User::where('email', $email)->where('role', 'user')->first();

        if (! $user) {
            $this->error("User dengan email {$email} tidak ditemukan.");
            return Command::FAILURE;
        }

        // Generate password acak 10 karakter
        $tempPassword = Str::random(10);

        $user->update([
            'password'              => Hash::make($tempPassword),
            'force_change_password' => true,
        ]);

        $this->info("✓ Password berhasil direset untuk: {$user->name}");
        $this->line("  Email    : {$email}");
        $this->line("  Password : <comment>{$tempPassword}</comment>");
        $this->warn("  User wajib ganti password saat login pertama kali.");

        return Command::SUCCESS;
    }
}
