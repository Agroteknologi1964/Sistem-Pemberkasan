<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TotpService;
use App\Services\TwoFactorChallenge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Alur login dua langkah:
 *   password benar + 2FA aktif  → TIDAK dapat JWT, hanya cookie challenge
 *   kode benar                  → dapat JWT
 *   kode salah                  → tetap tanpa JWT
 */
class TwoFactorLoginTest extends TestCase
{
    use RefreshDatabase;

    private TotpService $totp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->totp = app(TotpService::class);
    }

    private function makeAdmin(bool $with2fa = false, string $password = 'RahasiaKuat123'): User
    {
        $user = User::create([
            'name'                  => 'Admin 2FA',
            'email'                 => 'admin2fa@agrotek.test',
            'password'              => bcrypt($password),
            'role'                  => 'admin',
            'is_active'             => true,
            'force_change_password' => false,
        ]);

        if ($with2fa) {
            $user->forceFill([
                'two_factor_secret'       => $this->totp->generateSecret(),
                'two_factor_confirmed_at' => now(),
            ])->save();

            $user->generateRecoveryCodes();
        }

        return $user->fresh();
    }

    private function currentCode(User $user): string
    {
        return $this->totp->codeAt($user->two_factor_secret, intdiv(time(), TotpService::PERIOD));
    }


    /**
     * Ambil nilai cookie mentah dari header Set-Cookie.
     *
     * getCookie() mengembalikan objek Cookie yang sudah melewati EncryptCookies,
     * sedangkan nilai yang dibutuhkan untuk request berikutnya adalah string
     * mentah seperti yang dikirim server.
     */
    private function rawCookie($response, string $name): string
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $name) {
                return $cookie->getValue();
            }
        }

        $this->fail("Cookie '{$name}' tidak ditemukan pada response");
    }



    // ─── Tanpa 2FA: perilaku lama harus tetap jalan ──────────────────────────

    public function test_login_without_2fa_issues_jwt_immediately(): void
    {
        $this->makeAdmin(with2fa: false);

        $response = $this->post('/login', [
            'email'    => 'admin2fa@agrotek.test',
            'password' => 'RahasiaKuat123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $response->assertCookie('jwt_token');
    }

    // ─── Dengan 2FA: password saja tidak cukup ───────────────────────────────

    public function test_login_with_2fa_does_not_issue_jwt(): void
    {
        $this->makeAdmin(with2fa: true);

        $response = $this->post('/login', [
            'email'    => 'admin2fa@agrotek.test',
            'password' => 'RahasiaKuat123',
        ]);

        // Diarahkan ke challenge, BUKAN ke dashboard.
        $response->assertRedirect(route('two-factor.challenge'));

        // Tidak ada cookie jwt_token — ini inti proteksinya.
        $this->assertNull($response->getCookie('jwt_token', false), 'JWT tidak boleh diterbitkan sebelum 2FA terverifikasi');
        $response->assertCookie(TwoFactorChallenge::COOKIE);
    }

    public function test_wrong_password_does_not_start_challenge(): void
    {
        $this->makeAdmin(with2fa: true);

        $response = $this->post('/login', [
            'email'    => 'admin2fa@agrotek.test',
            'password' => 'password-salah-sekali',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertNull($response->getCookie(TwoFactorChallenge::COOKIE, false));
        $this->assertNull($response->getCookie('jwt_token', false));
    }

    public function test_valid_totp_code_issues_jwt(): void
    {
        $user = $this->makeAdmin(with2fa: true);

        // Mulai challenge
        $login = $this->post('/login', [
            'email'    => 'admin2fa@agrotek.test',
            'password' => 'RahasiaKuat123',
        ]);

        $challenge = $this->rawCookie($login, TwoFactorChallenge::COOKIE);

        $response = $this->withUnencryptedCookie(TwoFactorChallenge::COOKIE, $challenge)
            ->post('/two-factor', ['code' => $this->currentCode($user)]);

        $response->assertRedirect(route('admin.dashboard'));
        $response->assertCookie('jwt_token');
    }

    public function test_invalid_totp_code_is_rejected(): void
    {
        $user = $this->makeAdmin(with2fa: true);

        $login = $this->post('/login', [
            'email'    => 'admin2fa@agrotek.test',
            'password' => 'RahasiaKuat123',
        ]);
        $challenge = $this->rawCookie($login, TwoFactorChallenge::COOKIE);

        // Pakai kode yang pasti salah (bukan kode saat ini)
        $wrong = $this->currentCode($user) === '000000' ? '111111' : '000000';

        $response = $this->withUnencryptedCookie(TwoFactorChallenge::COOKIE, $challenge)
            ->post('/two-factor', ['code' => $wrong]);

        $response->assertSessionHasErrors('code');
        $this->assertNull($response->getCookie('jwt_token', false), 'kode salah tidak boleh menerbitkan JWT');
    }

    public function test_challenge_requires_cookie(): void
    {
        $this->get('/two-factor')->assertRedirect(route('login'));
    }

    public function test_challenge_cookie_cannot_be_forged(): void
    {
        $this->makeAdmin(with2fa: true);

        // Cookie asal-asalan harus ditolak, bukan menyebabkan error 500.
        $this->withUnencryptedCookie(TwoFactorChallenge::COOKIE, 'bukan-payload-sah')
            ->get('/two-factor')
            ->assertRedirect(route('login'));

        $this->withUnencryptedCookie(TwoFactorChallenge::COOKIE, 'bukan-payload-sah')
            ->post('/two-factor', ['code' => '123456'])
            ->assertRedirect(route('login'));
    }

    // ─── Kode pemulihan ──────────────────────────────────────────────────────

    public function test_recovery_code_works_once(): void
    {
        $user = $this->makeAdmin(with2fa: true);

        // Buat set kode baru agar kita tahu nilai plaintext-nya.
        $codes = $user->generateRecoveryCodes();
        $code  = $codes[0];

        $login = $this->post('/login', [
            'email'    => 'admin2fa@agrotek.test',
            'password' => 'RahasiaKuat123',
        ]);
        $challenge = $this->rawCookie($login, TwoFactorChallenge::COOKIE);

        $first = $this->withUnencryptedCookie(TwoFactorChallenge::COOKIE, $challenge)
            ->post('/two-factor', ['code' => $code]);
        $first->assertRedirect(route('admin.dashboard'));

        // Kode yang sama dipakai lagi harus GAGAL.
        $second = $this->withUnencryptedCookie(TwoFactorChallenge::COOKIE, $challenge)
            ->post('/two-factor', ['code' => $code]);
        $second->assertSessionHasErrors('code');

        // Jumlah kode berkurang dari 10 menjadi 9.
        $this->assertCount(9, $user->fresh()->two_factor_recovery_codes);
    }

    public function test_two_factor_secret_is_encrypted_at_rest(): void
    {
        $user = $this->makeAdmin(with2fa: true);
        $plainSecret = $user->two_factor_secret;

        // Baca kolom mentah dari database, tanpa cast model.
        $raw = \DB::table('users')->where('id', $user->id)->value('two_factor_secret');

        $this->assertNotSame($plainSecret, $raw, 'secret tidak boleh tersimpan plaintext');
        $this->assertStringNotContainsString($plainSecret, $raw);

        // Tapi harus bisa dibaca kembali lewat model.
        $this->assertSame($plainSecret, $user->fresh()->two_factor_secret);
    }

    public function test_secret_is_hidden_from_array_serialization(): void
    {
        $user = $this->makeAdmin(with2fa: true);
        $array = $user->toArray();

        $this->assertArrayNotHasKey('two_factor_secret', $array);
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $array);
    }
}
