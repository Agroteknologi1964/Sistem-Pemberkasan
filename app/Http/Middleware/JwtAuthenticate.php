<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        // Ambil token dari cookie HttpOnly atau Authorization header
        $token = $request->cookie('jwt_token')
            ?? $request->bearerToken();

        if (! $token) {
            return $this->unauthenticated($request);
        }

        try {
            JWTAuth::setToken($token);
            $user = JWTAuth::authenticate();

            if (! $user || ! $user->is_active) {
                return $this->unauthenticated($request);
            }

            auth()->setUser($user);

        } catch (TokenExpiredException $e) {
            // Coba auto-refresh token
            try {
                $newToken = JWTAuth::refresh($token);
                JWTAuth::setToken($newToken);
                $user = JWTAuth::authenticate();
                auth()->setUser($user);

                $response = $next($request);
                return $response->withCookie(
                    self::makeJwtCookie($newToken)
                );
            } catch (JWTException) {
                return $this->unauthenticated($request);
            }

        } catch (JWTException $e) {
            return $this->unauthenticated($request);
        }

        return $next($request);
    }

    private function unauthenticated(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        return redirect()->route('login')->withCookie(cookie()->forget('jwt_token'));
    }

    public static function makeJwtCookie(string $token): \Symfony\Component\HttpFoundation\Cookie
    {
        return cookie(
            name: 'jwt_token',
            value: $token,
            minutes: config('jwt.ttl', 1440),
            path: '/',
            domain: null,
            // Paksa Secure saat APP_ENV=production meski diakses lewat HTTP,
            // agar token tidak pernah terkirim tanpa TLS.
            secure: app()->isProduction(),
            httpOnly: true,
            sameSite: 'lax',
            raw: false,
        );
    }
}
