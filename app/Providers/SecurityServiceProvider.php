<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class SecurityServiceProvider extends ServiceProvider
{
    /**
     * Daftarkan rate limiter untuk endpoint yang rawan brute force.
     *
     * - `login`: 5 percobaan / menit per kombinasi email+IP, email case-insensitive.
     *   Setelah 5 kali gagal akun dikunci sementara (429) sampai jendela 1 menit lewat.
     * - `uploads`: batasi unggahan agar storage tidak dibanjiri.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $key = 'login:' . mb_strtolower((string) $request->input('email')) . '|' . $request->ip();

            return Limit::perMinute(5)->by($key)->response(function (Request $request, array $headers) {
                // Callback ini WAJIB mengembalikan response 429 (bukan redirect),
                // karena Laravel membungkusnya dalam HttpResponseException.
                // $headers hanya berisi X-RateLimit-*, bukan Retry-After.
                $retryAfter = max(1, ($headers['X-RateLimit-Reset'] ?? time() + 60) - time());

                if ($request->expectsJson()) {
                    return response()->json(
                        ['message' => 'Terlalu banyak percobaan login. Coba lagi nanti.'],
                        429,
                        $headers + ['Retry-After' => $retryAfter]
                    );
                }

                return redirect()
                    ->route('login')
                    ->withErrors([
                        'email' => "Terlalu banyak percobaan login. Coba lagi dalam {$retryAfter} detik.",
                    ])
                    ->withHeaders($headers + ['Retry-After' => $retryAfter]);
            });
        });

        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        // Semua endpoint API memakai limiter global agar JWT tidak bisa di-spam.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: ($request->ip() ?: 'anon'));
        });

        // Rate limit sederhana untuk halaman login GET (mengurangi abuse scanner).
        RateLimiter::for('login-page', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        // Kode 2FA hanya 6 digit: 1 juta kombinasi. Batasi 5 percobaan/menit
        // agar tidak bisa ditebak dalam waktu wajar masa berlaku challenge.
        RateLimiter::for('two-factor', function (Request $request) {
            $challenge = $request->cookie(\App\Services\TwoFactorChallenge::COOKIE);

            return Limit::perMinute(5)->by('2fa:' . ($challenge ? sha1($challenge) : $request->ip()))
                ->response(function (Request $request, array $headers) {
                    return back()->withErrors([
                        'code' => 'Terlalu banyak percobaan kode verifikasi. Tunggu satu menit.',
                    ])->withHeaders($headers);
                });
        });

        // Hindari query sampah ke database user model waktu cache kosong.
        User::preventLazyLoading(! $this->app->isProduction());
    }
}
