<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceChangePassword
{
    /**
     * Jika user belum ganti password default dari admin,
     * paksa redirect ke halaman ganti password.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (
            $user &&
            $user->isUser() &&
            $user->force_change_password &&
            ! $request->routeIs('user.profile.password') &&
            ! $request->routeIs('logout')
        ) {
            return redirect()->route('user.profile.password')
                ->with('warning', 'Anda wajib mengganti password sebelum menggunakan sistem.');
        }

        return $next($request);
    }
}
