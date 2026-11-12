<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;

/**
 * Menjembatani alur login dua langkah (password → kode 2FA).
 *
 * Setelah password benar, AuthController TIDAK langsung menerbitkan JWT.
 * Yang dikirim hanya cookie `2fa_challenge` yang berisi ID user terenkripsi
 * dan berumur pendek. Token JWT baru diterbitkan setelah kode 2FA valid.
 *
 * Kenapa tidak menyimpan status "setengah login" di session:
 * aplikasi ini stateless (JWT), dan session bisa dipakai untuk melewati
 * proteksi kalau cookie-nya dibagi. Challenge terenkripsi + kedaluwarsa
 * lebih sempit dampaknya.
 */
class TwoFactorChallenge
{
    public const COOKIE = '2fa_challenge';

    /** Challenge berlaku 5 menit — cukup untuk membuka aplikasi OTP. */
    public const TTL_SECONDS = 300;

    public function __construct(private TotpService $totp) {}

    /**
     * Buat payload challenge untuk user, lalu kembalikan sebagai cookie.
     */
    public function makeCookie(User $user): \Symfony\Component\HttpFoundation\Cookie
    {
        $payload = json_encode([
            'user_id'   => $user->getKey(),
            'expires_at' => time() + self::TTL_SECONDS,
            'nonce'     => bin2hex(random_bytes(8)),
        ]);

        return cookie(
            name: self::COOKIE,
            value: Crypt::encryptString($payload),
            minutes: (int) ceil(self::TTL_SECONDS / 60),
            path: '/',
            domain: null,
            secure: app()->isProduction(),
            httpOnly: true,
            sameSite: 'lax',
            raw: false,
        );
    }

    public function forgetCookie(): \Symfony\Component\HttpFoundation\Cookie
    {
        return cookie()->forget(self::COOKIE);
    }

    /**
     * Baca dan validasi challenge dari request.
     * Mengembalikan null bila tidak ada, tidak bisa didekripsi, atau kedaluwarsa.
     */
    public function resolveUser(?string $raw): ?User
    {
        if (blank($raw)) {
            return null;
        }

        try {
            $payload = json_decode(Crypt::decryptString($raw), true);
        } catch (\Throwable) {
            // Token terenkripsi rusak atau dari kunci APP_KEY lain.
            return null;
        }

        if (! is_array($payload) || empty($payload['user_id']) || empty($payload['expires_at'])) {
            return null;
        }

        if (time() > (int) $payload['expires_at']) {
            return null;
        }

        $user = User::find($payload['user_id']);

        // Akun bisa saja dinonaktifkan di sela-sela challenge berjalan.
        if (! $user || ! $user->is_active) {
            return null;
        }

        return $user;
    }

    /**
     * Verifikasi kode 6 digit, atau kode pemulihan bila diberikan.
     *
     * Kode pemulihan dikonsumsi (dihapus) begitu terpakai agar tidak bisa
     * dipakai ulang, dan diperlakukan sama kuatnya dengan kode TOTP.
     */
    public function verify(User $user, string $code): bool
    {
        $code = trim($code);

        if ($code === '' || empty($user->two_factor_secret)) {
            return false;
        }

        if ($this->totp->verify($user->two_factor_secret, $code)) {
            return true;
        }

        // Format kode pemulihan: 10 karakter alfanumerik (bisa mengandung '-').
        if (strlen(preg_replace('/[^A-Za-z0-9]/', '', $code)) >= 8) {
            return $user->consumeRecoveryCode($code);
        }

        return false;
    }
}
