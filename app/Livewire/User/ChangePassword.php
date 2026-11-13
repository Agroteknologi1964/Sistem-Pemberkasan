<?php

namespace App\Livewire\User;

use App\Rules\NotCommonPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class ChangePassword extends Component
{
    // Atribut #[Rule] sengaja TIDAK dipakai di properti: di Livewire 3 atribut
    // itu akan menimpa method rules() dan membuat kebijakan password lemah
    // kembali berlaku. Semua aturan didefinisikan di rules().
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public bool $success = false;

    /**
     * Kebijakan password: minimal 8 karakter, wajib huruf besar+kecil dan
     * angka, serta ditolak bila pernah bocor di internet.
     *
     * Untuk arsip surat yang bersifat kredensial, password 8 karakter tanpa
     * syarat apa pun terlalu lemah.
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'min:8'],
            'password'         => [
                'required',
                'string',
                'confirmed',
                // Rule::uncompromised() SENGAJA tidak dipakai: ia fail-open
                // saat API pwnedpasswords tidak bisa dihubungi, sehingga
                // password lemah tetap lolos. NotCommonPassword memeriksa
                // secara lokal tanpa ketergantungan jaringan.
                Password::min(8)->letters()->mixedCase()->numbers(),
                new NotCommonPassword(),
            ],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'password.min'           => 'Password baru minimal 8 karakter.',
            'password.letters'       => 'Password harus mengandung huruf.',
            'password.mixed'         => 'Password harus mengandung huruf besar dan kecil.',
            'password.numbers'       => 'Password harus mengandung angka.',
            'password.confirmed'     => 'Konfirmasi password tidak cocok.',
            'current_password.min'   => 'Password saat ini minimal 8 karakter.',
        ];
    }

    public function save()
    {
        $this->validate();

        $user = auth()->user();

        if (! Hash::check($this->current_password, $user->password)) {
            $this->addError('current_password', 'Password saat ini tidak cocok.');
            return;
        }

        // Password baru tidak boleh sama dengan yang lama.
        if (Hash::check($this->password, $user->password)) {
            $this->addError('password', 'Password baru harus berbeda dari password saat ini.');
            return;
        }

        $oldCookieToken = request()->cookie('jwt_token');

        $user->forceFill([
            'password'             => Hash::make($this->password),
            'force_change_password' => false,
            'password_changed_at'  => now(),
        ])->save();

        // ── Cabut sesi lama ──────────────────────────────────────────────────
        // Token JWT yang sedang dipakai di-invalidate (masuk blacklist) agar
        // token yang mungkin sudah dicuri tidak lagi berguna. User diminta
        // login ulang dengan password baru.
        if ($oldCookieToken) {
            try {
                JWTAuth::setToken($oldCookieToken)->invalidate();
            } catch (JWTException) {
                // Token sudah kedaluwarsa / sudah di-blacklist — bukan masalah.
            }
        }

        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->success = true;

        session()->flash('success', 'Password berhasil diubah. Silakan masuk kembali dengan password baru.');

        // Logout penuh: hapus cookie agar token lama tidak bisa dipakai lagi.
        // Redirector Livewire tidak punya withCookie(), jadi cookie dihapus
        // lewat Cookie::queue() pada response berikutnya.
        cookie()->queue(cookie()->forget('jwt_token'));

        return redirect()->route('login');
    }

    public function render()
    {
        return view('livewire.user.change-password');
    }
}
