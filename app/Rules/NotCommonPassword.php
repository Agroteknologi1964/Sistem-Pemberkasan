<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Menolak password yang sangat umum dipakai atau mirip data akun pengguna.
 *
 * Kenapa tidak memakai Rule::uncompromised() milik Laravel:
 *   1. Rule itu memanggil api.pwnedpasswords.com. Bila VPS tidak punya
 *      koneksi keluar (umum di jaringan kampus dengan firewall ketat),
 *      NotPwnedVerifier mengembalikan hasil kosong dan password LULUS —
 *      fail-open. Untuk arsip surat kredensial itu tidak boleh.
 *   2. Menambah ketergantungan layanan pihak ketiga pada jalur login.
 *
 * Daftar di bawah mencakup password yang muncul di hampir semua daftar
 * kebocoran. Pemeriksaan tambahan (panjang, campuran karakter) ditangani
 * oleh Rule::password() di komponen.
 */
class NotCommonPassword implements ValidationRule
{
    /**
     * Password yang paling sering muncul di kebocoran data publik.
     * Dibandingkan case-insensitive setelah karakter non-alfanumerik dibuang,
     * sehingga "P@ssw0rd123" tetap tertangkap.
     */
    private const COMMON = [
        'password', 'password1', 'password12', 'password123', 'password1234',
        'passw0rd', 'p@ssw0rd', 'p@ssword', 'admin', 'admin123', 'administrator',
        'qwerty', 'qwerty123', 'qwertyuiop', 'asdfghjkl', 'zxcvbnm',
        '123456', '1234567', '12345678', '123456789', '1234567890', '0123456789',
        'letmein', 'welcome', 'welcome1', 'monkey', 'dragon', 'iloveyou',
        'football', 'baseball', 'sunshine', 'princess', 'shadow', 'master',
        'superman', 'batman', 'trustno1', 'abc123', 'abcd1234', 'test1234',
        'pemberkasan', 'agroteknologi', 'agrotek', 'agrotek123', 'umm', 'umm123',
        'kampus', 'kampus123', 'mahasiswa', 'dosen', 'sistem123', 'sistem',
        'rahasia', 'rahasia123', 'indonesia', 'bismillah', 'jakarta', 'malang',
        'surat123', 'dokumen', 'dokumen123', 'secret', 'secret123',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        // Normalisasi: buang simbol, huruf kecil, buang angka di belakang
        // agar "Password123!" tetap tertangkap sebagai "password".
        $normalized = strtolower(preg_replace('/[^a-z0-9]/i', '', $value) ?? '');
        $withoutTrailingDigits = preg_replace('/\d+$/', '', $normalized) ?? $normalized;

        $user = auth()->user();

        if ($user) {
            // Password tidak boleh mengandung bagian email atau nama pengguna.
            $localPart = strtolower(strtok((string) $user->email, '@') ?: '');

            if (strlen($localPart) >= 4 && str_contains($normalized, $localPart)) {
                $fail('Password tidak boleh mengandung bagian dari alamat email Anda.');
                return;
            }

            $namePart = preg_replace('/[^a-z]/', '', strtolower((string) $user->name)) ?? '';
            if (strlen($namePart) >= 4 && str_contains($normalized, $namePart)) {
                $fail('Password tidak boleh mengandung nama Anda.');
                return;
            }
        }

        foreach (self::COMMON as $common) {
            // Cocokkan bentuk utuh maupun tanpa deret angka di belakang.
            if ($normalized === $common || $withoutTrailingDigits === $common) {
                $fail('Password ini terlalu umum dan mudah ditebak. Pilih kombinasi lain.');
                return;
            }

            // Juga tolak password umum yang menyisipkan angka di tengah
            // dan sangat sederhana (mis. "pass123word" tetap ditolak).
            if (strlen($common) >= 6 && str_contains($withoutTrailingDigits, $common)) {
                $fail('Password ini mengandung kata yang terlalu umum. Pilih kombinasi lain.');
                return;
            }
        }

        // Deret berurutan yang panjang mudah ditebak (12345678, abcdefg).
        if (preg_match('/(0123|1234|2345|3456|4567|5678|6789|abcd|bcde|cdef)/', $normalized)) {
            $fail('Hindari deret angka atau huruf berurutan seperti "1234" atau "abcd".');
            return;
        }

        // Satu karakter diulang terus tidak menambah kekuatan.
        if (preg_match('/(.)\1{3,}/', $normalized)) {
            $fail('Hindari karakter yang sama diulang lebih dari tiga kali.');
        }
    }
}
