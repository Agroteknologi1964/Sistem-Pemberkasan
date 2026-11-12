<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FileController;
use App\Http\Middleware\JwtAuthenticate;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Admin\FolderManager;
use App\Livewire\Admin\UserManager;
use App\Livewire\User\ChangePassword;
use App\Livewire\User\FolderBrowser;
use Illuminate\Support\Facades\Route;
use App\Livewire\Admin\ActivityLog;

// ── Publik ───────────────────────────────────────────────────────────────────

Route::get('/', fn () => redirect()->route('login'));

Route::middleware('guest.jwt')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])
        ->middleware('throttle:login-page')
        ->name('login');

    // Throttle login: 5 percobaan/menit per email+IP (lihat SecurityServiceProvider).
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('login.post');

    // Langkah kedua: verifikasi kode 2FA. Dibatasi lebih ketat karena kode
    // hanya 6 digit — tanpa throttle, brute force 1 juta kombinasi mungkin.
    Route::get('/two-factor', [AuthController::class, 'showTwoFactorChallenge'])
        ->middleware('throttle:login-page')
        ->name('two-factor.challenge');

    Route::post('/two-factor', [AuthController::class, 'verifyTwoFactor'])
        ->middleware('throttle:two-factor')
        ->name('two-factor.verify');
});

// Menggunakan Class custom agar membaca cookie
Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware(JwtAuthenticate::class)
    ->name('logout');

Route::post('/auth/refresh', [AuthController::class, 'refresh'])
    ->middleware(JwtAuthenticate::class)
    ->name('auth.refresh');

// ── Admin ────────────────────────────────────────────────────────────────────

// Menggunakan Class custom dipadukan dengan alias role
Route::middleware([JwtAuthenticate::class, 'jwt.fresh', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', AdminDashboard::class)->name('dashboard');
        Route::get('/users', UserManager::class)->name('users.index');
        Route::get('/folders', FolderManager::class)->name('folders.index');
        Route::get('/folders/{folderId}', FolderManager::class)->name('folders.show');
        Route::get('/files/{fileId}/preview', [FileController::class, 'preview'])->name('files.preview');
        Route::get('/files/{fileId}/download', [FileController::class, 'download'])->name('files.download');
        Route::get('/activity-logs', ActivityLog::class)->name('logs.index');
        Route::get('/profile/security', \App\Livewire\User\Security::class)->name('profile.security');

        // Admin juga harus bisa mengganti password sendiri (sebelumnya tidak ada UI).
        Route::get('/profile/password', ChangePassword::class)->name('profile.password');
    });

// ── User ─────────────────────────────────────────────────────────────────────

Route::middleware([JwtAuthenticate::class, 'jwt.fresh', 'role:user'])
    ->prefix('user')
    ->name('user.')
    ->group(function () {
        // Halaman ganti password (tidak kena force.pass agar tidak redirect loop)
        Route::get('/profile/password', ChangePassword::class)->name('profile.password');

        // Pengaturan keamanan (2FA) — juga di luar force.pass agar bisa diakses
        // sebelum password wajib diganti, dan tetap ada walau 2FA sudah aktif.
        Route::get('/profile/security', \App\Livewire\User\Security::class)->name('profile.security');

        // Route lain wajib sudah ganti password
        Route::middleware('force.pass')->group(function () {
            Route::get('/dashboard', function () {
                $root = auth()->user()->rootFolder;
                abort_unless($root, 404, 'Folder tidak ditemukan. Hubungi admin.');
                return redirect()->route('user.folders.show', $root->id);
            })->name('dashboard');

            Route::get('/folders/{folderId}', FolderBrowser::class)->name('folders.show');
            Route::get('/files/{fileId}/preview', [FileController::class, 'preview'])->name('files.preview');
            Route::get('/files/{fileId}/download', [FileController::class, 'download'])->name('files.download');
        });
    });