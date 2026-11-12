<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kebijakan password dan pencabutan sesi setelah password diganti.
 */
class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $password = 'PasswordLama123'): User
    {
        return User::create([
            'name'                  => 'User Pass',
            'email'                 => 'userpass@agrotek.test',
            'password'              => bcrypt($password),
            'role'                  => 'user',
            'is_active'             => true,
            'force_change_password' => false,
        ]);
    }







    // ─── Kebijakan password ──────────────────────────────────────────────────

    public function test_short_password_is_rejected(): void
    {
        $user = $this->makeUser();

        Livewire::actingAs($user)
            ->test(\App\Livewire\User\ChangePassword::class)
            ->set('current_password', 'PasswordLama123')
            ->set('password', 'Abc123')          // 6 karakter
            ->set('password_confirmation', 'Abc123')
            ->call('save')
            ->assertHasErrors('password');
    }

    public function test_password_without_numbers_is_rejected(): void
    {
        $user = $this->makeUser();

        Livewire::actingAs($user)
            ->test(\App\Livewire\User\ChangePassword::class)
            ->set('current_password', 'PasswordLama123')
            ->set('password', 'TanpaAngkaSamaSekali')
            ->set('password_confirmation', 'TanpaAngkaSamaSekali')
            ->call('save')
            ->assertHasErrors('password');
    }

    public function test_password_without_mixed_case_is_rejected(): void
    {
        $user = $this->makeUser();

        Livewire::actingAs($user)
            ->test(\App\Livewire\User\ChangePassword::class)
            ->set('current_password', 'PasswordLama123')
            ->set('password', 'semuahurufkecil123')
            ->set('password_confirmation', 'semuahurufkecil123')
            ->call('save')
            ->assertHasErrors('password');
    }

    public function test_mismatched_confirmation_is_rejected(): void
    {
        $user = $this->makeUser();

        Livewire::actingAs($user)
            ->test(\App\Livewire\User\ChangePassword::class)
            ->set('current_password', 'PasswordLama123')
            ->set('password', 'PasswordBaru123')
            ->set('password_confirmation', 'PasswordBaru126')
            ->call('save')
            ->assertHasErrors('password');
    }

    public function test_same_as_old_password_is_rejected(): void
    {
        $user = $this->makeUser('PasswordLama123');

        Livewire::actingAs($user)
            ->test(\App\Livewire\User\ChangePassword::class)
            ->set('current_password', 'PasswordLama123')
            ->set('password', 'PasswordLama123')
            ->set('password_confirmation', 'PasswordLama123')
            ->call('save')
            ->assertHasErrors('password');
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = $this->makeUser();

        // Password baru yang valid, tapi password lama salah.
        Livewire::actingAs($user)
            ->test(\App\Livewire\User\ChangePassword::class)
            ->set('current_password', 'BukanPasswordSaya1')
            ->set('password', 'Xk7mQv2Lp9')
            ->set('password_confirmation', 'Xk7mQv2Lp9')
            ->call('save')
            ->assertHasErrors('current_password');

        // Password tidak boleh berubah.
        $this->assertFalse(\Hash::check('Xk7mQv2Lp9', $user->fresh()->password));
    }

    // ─── Perubahan berhasil + pencabutan sesi ────────────────────────────────

    public function test_valid_password_change_updates_password(): void
    {
        $user = $this->makeUser('PasswordLama123');

        Livewire::actingAs($user)
            ->test(\App\Livewire\User\ChangePassword::class)
            ->set('current_password', 'PasswordLama123')
            ->set('password', 'Xk7mQv2Lp9')
            ->set('password_confirmation', 'Xk7mQv2Lp9')
            ->call('save')
            ->assertHasNoErrors();

        $fresh = $user->fresh();
        $this->assertTrue(Hash::check('Xk7mQv2Lp9', $fresh->password));
        $this->assertFalse(Hash::check('PasswordLama123', $fresh->password));
    }

    public function test_password_change_records_timestamp(): void
    {
        $user = $this->makeUser('PasswordLama123');
        $this->assertNull($user->password_changed_at);

        Livewire::actingAs($user)
            ->test(\App\Livewire\User\ChangePassword::class)
            ->set('current_password', 'PasswordLama123')
            ->set('password', 'Xk7mQv2Lp9')
            ->set('password_confirmation', 'Xk7mQv2Lp9')
            ->call('save');

        $this->assertNotNull(
            $user->fresh()->password_changed_at,
            'password_changed_at harus diisi agar token lama bisa ditolak'
        );
    }

    public function test_password_change_logs_user_out(): void
    {
        $user = $this->makeUser('PasswordLama123');

        Livewire::actingAs($user)
            ->test(\App\Livewire\User\ChangePassword::class)
            ->set('current_password', 'PasswordLama123')
            ->set('password', 'Xk7mQv2Lp9')
            ->set('password_confirmation', 'Xk7mQv2Lp9')
            ->call('save')
            ->assertRedirect(route('login'));
    }

    // ─── Middleware penolak token lama ───────────────────────────────────────

    public function test_token_issued_before_password_change_is_rejected(): void
    {
        $user = $this->makeUser('PasswordLama123');

        // Token diterbitkan sekarang...
        $oldToken = auth('api')->login($user);

        // ...lalu password diganti 10 detik kemudian.
        $user->forceFill(['password_changed_at' => now()->addSeconds(10)])->save();

        // Token lama harus ditolak, bukan meloloskan request ke dashboard.
        $response = $this->withUnencryptedCookie('jwt_token', $oldToken)
            ->get('/user/profile/password');

        $response->assertRedirect(route('login'));
    }

    public function test_token_issued_after_password_change_is_accepted(): void
    {
        $user = $this->makeUser('PasswordLama123');

        // Password diganti dulu...
        $user->forceFill(['password_changed_at' => now()->subSeconds(60)])->save();

        // ...baru token diterbitkan.
        $fresh = $user->fresh();
        $token = auth('api')->login($fresh);

        $this->withUnencryptedCookie('jwt_token', $token)
            ->get('/user/profile/password')
            ->assertOk();
    }
}
