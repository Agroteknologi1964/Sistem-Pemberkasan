<?php

namespace App\Services;

/**
 * Implementasi TOTP (RFC 6238) dan Base32 (RFC 4648) tanpa dependency eksternal.
 *
 * Alasan tidak memakai paket seperti pragmarx/google2fa:
 *   - ext-gmp tidak tersedia di image PHP-FPM kita, dan paket itu butuh GMP
 *     untuk sebagian besar operasi BigNumber.
 *   - Logika TOTP cukup sederhana: HMAC-SHA1 atas timestamp/30, lalu ambil
 *     6 digit dengan dynamic truncation. Lebih mudah diaudit sendiri.
 *
 * Kompatibel dengan Google Authenticator, Microsoft Authenticator, Authy,
 * dan aplikasi OTP standar lainnya.
 */
class TotpService
{
    /** Panjang kode yang diminta ke pengguna. */
    public const DIGITS = 6;

    /** Jendela waktu satu kode, dalam detik. */
    public const PERIOD = 30;

    /** Toleransi selisih jam: cek N langkah ke belakang dan ke depan. */
    public const WINDOW = 1;

    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Buat secret acak yang aman, dikodekan Base32.
     *
     * 20 byte = 160 bit, sesuai rekomendasi RFC 4226 untuk HMAC-SHA1.
     */
    public function generateSecret(int $bytes = 20): string
    {
        return $this->base32Encode(random_bytes($bytes));
    }

    /**
     * Bangun otpauth:// URL untuk dipindai aplikasi authenticator.
     */
    public function provisioningUri(string $secret, string $accountName, string $issuer): string
    {
        $label = rawurlencode($issuer) . ':' . rawurlencode($accountName);

        $params = http_build_query([
            'secret'    => $secret,
            'issuer'    => $issuer,
            'algorithm' => 'SHA1',
            'digits'    => self::DIGITS,
            'period'    => self::PERIOD,
        ], '', '&', PHP_QUERY_RFC3986);

        return "otpauth://totp/{$label}?{$params}";
    }

    /**
     * Verifikasi kode 6 digit terhadap secret.
     *
     * Menerima selisih ±WINDOW langkah agar pengguna tidak gagal hanya karena
     * jam ponselnya bergeser beberapa detik.
     */
    public function verify(string $secret, string $code, ?int $timestamp = null): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';

        if (strlen($code) !== self::DIGITS) {
            return false;
        }

        $timestamp ??= time();
        $counter    = intdiv($timestamp, self::PERIOD);

        // Bandingkan semua jendela tanpa early-return agar waktu eksekusi
        // tidak membocorkan posisi langkah yang cocok.
        $matched = false;
        for ($offset = -self::WINDOW; $offset <= self::WINDOW; $offset++) {
            if (hash_equals($this->codeAt($secret, $counter + $offset), $code)) {
                $matched = true;
            }
        }

        return $matched;
    }

    /**
     * Hitung kode TOTP untuk counter tertentu.
     */
    public function codeAt(string $secret, int $counter): string
    {
        $key = $this->base32Decode($secret);

        // Counter 64-bit big-endian sesuai RFC 4226.
        $binaryCounter = pack('J', $counter);

        $hash   = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord($hash[19]) & 0x0F;

        $part = substr($hash, $offset, 4);
        $value = unpack('N', $part)[1] & 0x7FFFFFFF;

        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    // ─── Base32 ──────────────────────────────────────────────────────────────

    public function base32Encode(string $binary): string
    {
        $bits   = '';
        $output = '';

        for ($i = 0, $len = strlen($binary); $i < $len; $i++) {
            $bits .= str_pad(decbin(ord($binary[$i])), 8, '0', STR_PAD_LEFT);
        }

        foreach (str_split($bits, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0');
            $output .= self::ALPHABET[bindec($chunk)];
        }

        return $output;
    }

    public function base32Decode(string $encoded): string
    {
        $encoded = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $encoded) ?? '');
        $bits    = '';

        for ($i = 0, $len = strlen($encoded); $i < $len; $i++) {
            $pos = strpos(self::ALPHABET, $encoded[$i]);
            if ($pos === false) {
                continue;
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $binary .= chr(bindec($byte));
            }
        }

        return $binary;
    }
}
