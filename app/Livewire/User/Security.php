<?php

namespace App\Livewire\User;

use App\Models\User;
use App\Services\TotpService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Rule;
use Livewire\Component;

/**
 * Halaman pengaturan keamanan akun:
 *   - mengaktifkan / mematikan 2FA
 *   - menampilkan dan memperbarui kode pemulihan
 */
class Security extends Component
{
    public bool $showEnableModal = false;

    /** Secret sementara — belum disimpan sampai user mengonfirmasi kode. */
    public string $pendingSecret = '';
    public string $provisioningUri = '';

    #[Rule('required|string|max:20')]
    public string $confirmCode = '';

    /** Kode pemulihan plaintext — hanya ditampilkan sekali setelah setup. */
    public array $recoveryCodes = [];
    public bool $showRecoveryCodes = false;

    #[Rule('required|string|min:8')]
    public string $disablePassword = '';
    public bool $showDisableModal = false;

    public function mount(): void
    {
        // Pastikan hanya halaman sendiri — tidak ada parameter ID yang bisa
        // disalahgunakan untuk melihat secret akun lain.
        abort_unless(auth()->check(), 403);
    }

    // ─── Aktifkan 2FA ────────────────────────────────────────────────────────

    public function startEnable(): void
    {
        $user = $this->user();

        if ($user->hasTwoFactorEnabled()) {
            $this->addError('enable', '2FA sudah aktif pada akun ini.');
            return;
        }

        $totp = app(TotpService::class);

        $this->pendingSecret   = $totp->generateSecret();
        $this->provisioningUri = $totp->provisioningUri(
            $this->pendingSecret,
            $user->email,
            config('app.name', 'Sistem Pemberkasan'),
        );
        $this->confirmCode      = '';
        $this->showEnableModal  = true;
    }

    public function confirmEnable(): void
    {
        $this->validateOnly('confirmCode');

        $user = $this->user();

        if ($this->pendingSecret === '') {
            $this->addError('confirmCode', 'Sesi pengaturan habis. Mulai ulang.');
            return;
        }

        // Kode salah tidak boleh menulis apa pun ke database — user tetap
        // dalam keadaan belum punya 2FA sampai confirmasi berhasil.
        if (! app(TotpService::class)->verify($this->pendingSecret, $this->confirmCode)) {
            $this->addError('confirmCode', 'Kode tidak cocok. Pastikan jam perangkat Anda benar.');
            return;
        }

        $user->forceFill([
            'two_factor_secret'       => $this->pendingSecret,
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->recoveryCodes     = $user->generateRecoveryCodes();
        $this->showRecoveryCodes = true;

        $this->showEnableModal = false;
        $this->pendingSecret   = '';
        $this->provisioningUri = '';
        $this->confirmCode     = '';

        $this->dispatch('notify', message: 'Verifikasi dua langkah berhasil diaktifkan.');
    }

    // ─── Matikan 2FA ─────────────────────────────────────────────────────────

    public function startDisable(): void
    {
        $this->disablePassword   = '';
        $this->showDisableModal  = true;
    }

    public function confirmDisable(): void
    {
        $this->validateOnly('disablePassword');

        $user = $this->user();

        // Mematikan 2FA menurunkan keamanan akun secara signifikan —
        // wajib konfirmasi password, bukan hanya klik tombol.
        if (! Hash::check($this->disablePassword, $user->password)) {
            $this->addError('disablePassword', 'Password tidak cocok.');
            return;
        }

        $user->forceFill([
            'two_factor_secret'          => null,
            'two_factor_confirmed_at'    => null,
            'two_factor_recovery_codes'  => null,
        ])->save();

        $this->showDisableModal = false;
        $this->disablePassword  = '';
        $this->recoveryCodes    = [];
        $this->showRecoveryCodes = false;

        $this->dispatch('notify', message: 'Verifikasi dua langkah dimatikan.');
    }

    // ─── Kode pemulihan ──────────────────────────────────────────────────────

    public function regenerateRecoveryCodes(): void
    {
        $user = $this->user();

        if (! $user->hasTwoFactorEnabled()) {
            return;
        }

        $this->recoveryCodes     = $user->generateRecoveryCodes();
        $this->showRecoveryCodes = true;

        $this->dispatch('notify', message: 'Kode pemulihan baru dibuat. Kode lama tidak berlaku.');
    }

    public function hideRecoveryCodes(): void
    {
        $this->recoveryCodes     = [];
        $this->showRecoveryCodes = false;
    }

    // ─── Render ──────────────────────────────────────────────────────────────

    public function render()
    {
        return view('livewire.user.security', [
            'user' => $this->user(),
        ]);
    }

    private function user(): User
    {
        return Auth::user();
    }
}
