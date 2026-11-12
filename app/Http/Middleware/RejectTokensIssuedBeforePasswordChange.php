<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menolak JWT yang diterbitkan SEBELUM password terakhir diganti.
 *
 * Skenario yang ditutup: penyerang mencuri token, lalu korban mengganti
 * password. Tanpa pemeriksaan ini, token curian tetap sah sampai TTL habis
 * (24 jam). Dengan iat (issued at) dibandingkan ke password_changed_at,
 * token lama langsung mati begitu password berubah.
 *
 * Ditempatkan SETELAH JwtAuthenticate (yang sudah memvalidasi tanda tangan
 * dan mengisi auth()->user()).
 */
class RejectTokensIssuedBeforePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        // JwtAuthenticate mengisi auth()->user() pada guard default. Guard api
        // dipakai eksplisit agar tidak bergantung pada urutan boot config.
        $user = auth()->user() ?? auth('api')->user();

        if (! $user || ! $user->password_changed_at) {
            return $next($request);
        }

        try {
            // Token diambil dari cookie / Bearer, sama seperti JwtAuthenticate.
            $token = $request->cookie('jwt_token') ?? $request->bearerToken();

            if (! $token) {
                return $next($request);
            }

            $payload  = \Tymon\JWTAuth\Facades\JWTAuth::setToken($token)->getPayload();
            $issuedAt = $payload->get('iat');
        } catch (\Throwable) {
            // Token tidak bisa diparse — biarkan JwtAuthenticate yang menangani.
            return $next($request);
        }

        if ($issuedAt === null || (int) $issuedAt >= $user->password_changed_at->timestamp) {
            return $next($request);
        }

        // Token lebih tua dari waktu ganti password → tidak berlaku lagi.
        try {
            \Tymon\JWTAuth\Facades\JWTAuth::setToken($token)->invalidate();
        } catch (\Throwable) {
            // Sudah tidak bisa di-invalidate; penolakan di bawah tetap berlaku.
        }

        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Sesi berakhir karena password diubah. Silakan masuk kembali.',
            ], 401);
        }

        return redirect()
            ->route('login')
            ->withErrors(['email' => 'Password Anda baru saja diubah. Silakan masuk kembali.'])
            ->withCookie(cookie()->forget('jwt_token'));
    }
}
