<?php

namespace Tests\Unit;

use App\Services\TotpService;
use PHPUnit\Framework\TestCase;

/**
 * Validasi implementasi TOTP terhadap test vector resmi RFC 6238 Appendix B.
 * Kalau ini gagal, jangan pakai fitur 2FA-nya: kode yang dihasilkan tidak akan
 * cocok dengan Google Authenticator.
 */
class TotpServiceTest extends TestCase
{
    private TotpService $totp;

    /** Secret uji dari RFC 6238: ASCII "12345678901234567890". */
    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        $this->totp   = new TotpService();
        $this->secret = $this->totp->base32Encode('12345678901234567890');
    }

    /**
     * RFC 6238 Appendix B memakai kode 8 digit. Implementasi kita 6 digit,
     * jadi yang dibandingkan adalah 6 digit terakhir — nilainya identik karena
     * truncation menghasilkan angka yang sama, hanya lebarnya beda.
     */
    public function test_matches_rfc6238_sha1_vectors(): void
    {
        $vectors = [
            59          => '94287082',
            1111111109  => '07081804',
            1111111111  => '14050471',
            1234567890  => '89005924',
            2000000000  => '69279037',
            20000000000 => '65353130',
        ];

        foreach ($vectors as $timestamp => $expected8) {
            $actual = $this->totp->codeAt($this->secret, intdiv($timestamp, TotpService::PERIOD));

            $this->assertSame(
                substr($expected8, -TotpService::DIGITS),
                substr($actual, -TotpService::DIGITS),
                "TOTP tidak cocok pada timestamp {$timestamp}"
            );
        }
    }

    public function test_base32_roundtrip(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $raw = random_bytes(random_int(1, 40));
            $this->assertSame($raw, $this->totp->base32Decode($this->totp->base32Encode($raw)));
        }
    }

    public function test_base32_alphabet_is_valid(): void
    {
        $secret = $this->totp->generateSecret(20);

        $this->assertSame(32, strlen($secret), '20 byte harus jadi 32 karakter base32');
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
    }

    public function test_verify_accepts_current_code(): void
    {
        $now  = 1700000000;
        $code = $this->totp->codeAt($this->secret, intdiv($now, TotpService::PERIOD));

        $this->assertTrue($this->totp->verify($this->secret, $code, $now));
    }

    public function test_verify_tolerates_one_step_drift(): void
    {
        $now  = 1700000000;
        $step = TotpService::PERIOD;

        $prevCode = $this->totp->codeAt($this->secret, intdiv($now - $step, TotpService::PERIOD));
        $nextCode = $this->totp->codeAt($this->secret, intdiv($now + $step, TotpService::PERIOD));

        $this->assertTrue($this->totp->verify($this->secret, $prevCode, $now), 'kode 30 detik lalu harus diterima');
        $this->assertTrue($this->totp->verify($this->secret, $nextCode, $now), 'kode 30 detik ke depan harus diterima');
    }

    public function test_verify_rejects_code_outside_window(): void
    {
        $now = 1700000000;

        // 3 langkah ke belakang berada di luar toleransi ±1.
        $old = $this->totp->codeAt($this->secret, intdiv($now - (3 * TotpService::PERIOD), TotpService::PERIOD));

        $this->assertFalse($this->totp->verify($this->secret, $old, $now));
    }

    public function test_verify_rejects_malformed_input(): void
    {
        $now = 1700000000;

        $this->assertFalse($this->totp->verify($this->secret, '', $now));
        $this->assertFalse($this->totp->verify($this->secret, '12345', $now), '5 digit harus ditolak');
        $this->assertFalse($this->totp->verify($this->secret, '1234567', $now), '7 digit harus ditolak');
        $this->assertFalse($this->totp->verify($this->secret, 'abcdef', $now), 'huruf harus ditolak');
    }

    public function test_provisioning_uri_escapes_issuer_and_account(): void
    {
        $uri = $this->totp->provisioningUri('ABCDEF234567', 'admin@agrotek.com', 'Sistem Pemberkasan');

        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret=ABCDEF234567', $uri);
        $this->assertStringContainsString('digits=6', $uri);
        $this->assertStringContainsString('period=30', $uri);
        $this->assertStringNotContainsString(' ', $uri, 'spasi harus sudah di-encode');
    }
}
