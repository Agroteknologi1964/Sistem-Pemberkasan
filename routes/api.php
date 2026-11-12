<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (opsional, untuk integrasi mobile atau third-party)
|--------------------------------------------------------------------------
|
| Semua route di sini prefix /api secara otomatis.
| Auth menggunakan JWT Bearer token di header Authorization.
|
*/

Route::prefix('auth')->middleware('throttle:login')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('api.login');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('jwt.auth')->name('api.logout');
    Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('jwt.auth')->name('api.refresh');
});

// Contoh endpoint API yang dilindungi JWT
Route::middleware(['jwt.auth', 'throttle:api'])->group(function () {
    Route::get('/me', function () {
        return response()->json([
            'user' => auth()->user()->only(['id', 'name', 'email', 'role']),
        ]);
    });
});
