<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menambahkan security header HTTP ke setiap response.
 *
 * Header ini mencegah XSS reflected, clickjacking, MIME sniffing, dan
 * kebocoran Referer ke domain luar.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Frame-Options'        => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy'        => 'strict-origin-when-cross-origin',
            'Permissions-Policy'     => 'geolocation=(), microphone=(), camera=()',
            'X-XSS-Protection'       => '0', // legacy header; CSP di bawah ambil alih
        ];

        // HSTS hanya dikirim saat produksi HTTPS — kalau dikirim di HTTP lokal
        // browser akan memaksa HTTPS ke localhost dan server mati tak bisa diakses.
        if ($request->secure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        // CSP longgar: CDN Tailwind + Livewire/Alpine membutuhkan inline script.
        // 'unsafe-inline' untuk script sengaja dipakai karena Livewire menanam
        // inline bootstrap; jangan hapus tanpa memindahkan seluruh script ke file.
        $headers['Content-Security-Policy'] = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com",
            "style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://fonts.bunny.net",
            "font-src 'self' data: https://fonts.bunny.net",
            "img-src 'self' data: blob:",
            "connect-src 'self'",
            "frame-src 'self' blob:",
            "object-src 'self' blob:",
            "base-uri 'self'",
            "form-action 'self'",
        ]);

        foreach ($headers as $key => $value) {
            if (! $response->headers->has($key)) {
                $response->headers->set($key, $value);
            }
        }

        return $response;
    }
}
