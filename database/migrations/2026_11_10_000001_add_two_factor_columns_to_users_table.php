<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom untuk 2FA (TOTP) dan pelacakan pergantian password.
 *
 * two_factor_secret disimpan TERENKRIPSI (cast 'encrypted' di model User),
 * bukan plaintext — kalau database bocor, secret TOTP tidak bisa dipakai
 * untuk membangkitkan kode.
 *
 * password_changed_at dipakai untuk mencabut token JWT yang diterbitkan
 * SEBELUM password diganti.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');

            // Kode pemulihan disimpan terenkripsi sebagai JSON array, di-hash
            // satu per satu supaya tidak bisa dipakai ulang.
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_confirmed_at');

            $table->timestamp('password_changed_at')->nullable()->after('two_factor_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_confirmed_at',
                'two_factor_recovery_codes',
                'password_changed_at',
            ]);
        });
    }
};
