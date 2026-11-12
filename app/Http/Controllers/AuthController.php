<?php

namespace App\Http\Controllers;

use App\Http\Middleware\JwtAuthenticate;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\TwoFactorChallenge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function __construct(private TwoFactorChallenge $twoFactor) {}
    /**
     * Tampilkan halaman login.
     */
    public function showLogin()
    {
        $token = request()->cookie('jwt_token');
        if ($token){
            try{
                JWTAuth::setToken($token);
                $user = JWTAuth::authenticate();
                if ($user && $user->is_active) {
                    return $this->redirectByRole();
                }
            } catch (JWTException) {
                // Token tidak valid, abaikan dan tampilkan halaman login
            }
        }

        return view('auth.login');
    }

    /**
     * Proses login, issue JWT, simpan di cookie HttpOnly.
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('email', 'password');

        try {
            if (! $token = JWTAuth::attempt($credentials)) {
                return back()->withErrors(['email' => 'Email atau password salah.'])->withInput();
            }
        } catch (JWTException $e) {
            return back()->withErrors(['email' => 'Tidak dapat membuat token, coba lagi.']);
        }

        $user = auth()->user();

        if (! $user->is_active) {
            JWTAuth::invalidate($token);
            return back()->withErrors(['email' => 'Akun Anda tidak aktif. Hubungi Administrator.'])->withInput();
        }

        // ── Langkah kedua: 2FA ───────────────────────────────────────────────
        // Token yang baru dibuat langsung dibatalkan. JWT hanya diterbitkan
        // setelah kode TOTP / kode pemulihan terbukti valid.
        if ($user->hasTwoFactorEnabled()) {
            // invalidate() butuh token aktif di instance; tanpa setToken()
            // ia melempar "A token is required". Dibungkus try/catch karena
            // blacklist bisa dinonaktifkan dan kegagalan di sini tidak boleh
            // menggagalkan alur login.
            try {
                JWTAuth::setToken($token)->invalidate();
            } catch (JWTException) {
                // Token tidak bisa di-blacklist (mis. blacklist nonaktif).
                // Tidak masalah: cookie belum pernah dikirim ke browser,
                // jadi token ini tidak bisa dipakai siapa pun.
            }

            return redirect()
                ->route('two-factor.challenge')
                ->withCookie($this->twoFactor->makeCookie($user));
        }

        return $this->redirectByRole()->withCookie(JwtAuthenticate::makeJwtCookie($token));
    }

    /**
     * Tampilkan form permintaan kode 2FA.
     */
    public function showTwoFactorChallenge(Request $request)
    {
        if (! $this->twoFactor->resolveUser($request->cookie(TwoFactorChallenge::COOKIE))) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Sesi verifikasi kedaluwarsa. Silakan masuk kembali.'])
                ->withCookie($this->twoFactor->forgetCookie());
        }

        return view('auth.two-factor');
    }

    /**
     * Verifikasi kode 2FA, lalu terbitkan JWT.
     */
    public function verifyTwoFactor(Request $request): RedirectResponse
    {
        $request->validate(
            ['code' => 'required|string|max:20'],
            [
                'code.required' => 'Kode verifikasi wajib diisi.',
                'code.max'      => 'Kode verifikasi terlalu panjang.',
            ]
        );

        $user = $this->twoFactor->resolveUser($request->cookie(TwoFactorChallenge::COOKIE));

        if (! $user) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Sesi verifikasi kedaluwarsa. Silakan masuk kembali.'])
                ->withCookie($this->twoFactor->forgetCookie());
        }

        if (! $this->twoFactor->verify($user, (string) $request->input('code'))) {
            // Pesan sengaja tidak membedakan "kode salah" vs "kode kedaluwarsa".
            return back()->withErrors([
                'code' => 'Kode verifikasi tidak valid atau sudah kedaluwarsa.',
            ]);
        }

        // Kunci challenge: buat token baru untuk user, bukan memakai token lama.
        $token = JWTAuth::fromUser($user);
        auth()->setUser($user);

        return $this->redirectByRole()
            ->withCookie(JwtAuthenticate::makeJwtCookie($token))
            ->withCookie($this->twoFactor->forgetCookie());
    }

    /**
     * Logout: invalidate token dan hapus cookie.
     */
    public function logout(): RedirectResponse
    {
        // try {
        //     JWTAuth::invalidate(JWTAuth::getToken());
        // } catch (JWTException) {
        //     // Token sudah invalid, abaikan
        // }

        // return redirect()->route('login')
        //     ->withCookie(cookie()->forget('jwt_token'));

        $token = request()->cookie('jwt_token');

        if ($token) {
            try {
                JWTAuth::setToken($token)->invalidate();
            } catch (JWTException) {
                // Token sudah kadaluarsa, abaikan
            }
        }

        return redirect()->route('login')
            ->withCookie(cookie()->forget('jwt_token'));    
    }

    /**
     * Refresh JWT token (untuk AJAX heartbeat).
     */
    // public function refresh(): JsonResponse
    // {
    //     try {
    //         $token = JWTAuth::refresh(JWTAuth::getToken());
    //         return response()->json(['token' => $token]);
    //     } catch (JWTException $e) {
    //         return response()->json(['error' => 'Token tidak dapat diperbarui'], 401);
    //     }
    // }

    public function refresh(): JsonResponse
    {
        $token = request()->cookie('jwt_token');

        if (! $token) {
            return response()->json(['error' => 'Token tidak ditemukan.'], 401);
        }
        
        try {
            $newToken = JWTAuth::setToken($token)->refresh();
            return response()->json(['success' => true])
                ->withCookie(JwtAuthenticate::makeJwtCookie($newToken));
        } catch (JWTException) {
            return response()->json(['error' => 'Token tidak dapat diperbarui.'], 401);
        }
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    private function redirectByRole(): RedirectResponse
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        // User diarahkan ke root folder mereka
        $rootFolder = $user->rootFolder;
        if ($rootFolder) {
            return redirect()->route('user.folders.show', $rootFolder->id);
        }

        return redirect()->route('login')->withErrors(['email' => 'Folder tidak ditemukan, hubungi admin.']);
    }
}
