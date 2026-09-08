<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\ForceChangePassword;
use App\Http\Middleware\JwtAuthenticate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: [
            'jwt_token',
        ]);

        // Alias middleware agar bisa dipakai di routes
        $middleware->alias([
            'jwt.auth'    => JwtAuthenticate::class,
            'role'        => CheckRole::class,
            'guest.jwt'   => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'force.pass'  => ForceChangePassword::class,
        ]);

        // Middleware global yang jalan di semua request web
        $middleware->web(append: [
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {

        // Jika unauthenticated dan bukan request JSON, redirect ke login
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            if (! $request->expectsJson()) {
                return redirect()->route('login');
            }
        });

        // 403 Forbidden
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e, Request $request) {
            if (! $request->expectsJson()) {
                abort(403);
            }
        });

    })->create();
