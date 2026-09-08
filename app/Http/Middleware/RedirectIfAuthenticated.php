<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class RedirectIfAuthenticated
{
    /**
     * Jika user sudah login (token valid), redirect ke dashboard sesuai role.
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $token = $request->cookie('jwt_token');

        if ($token) {
            try {
                JWTAuth::setToken($token);
                $user = JWTAuth::authenticate();

                if ($user && $user->is_active) {
                    auth()->setUser($user);

                    if ($user->isAdmin()) {
                        return redirect()->route('admin.dashboard');
                    }

                    $rootFolder = $user->rootFolder;
                    if ($rootFolder) {
                        return redirect()->route('user.folders.show', $rootFolder->id);
                    }
                }
            } catch (JWTException) {
                // Token invalid, lanjut ke halaman login
            }
        }

        return $next($request);
    }
}
