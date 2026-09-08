<?php

namespace App\Providers;

use App\Http\Middleware\JwtAuthenticate;
use App\Models\User;
use App\Observers\UserObserver;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Tymon\JWTAuth\Facades\JWTAuth;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Livewire::setUpdateRoute(function ($handle){
            return Route::post('/Livewire/update', $handle)->middleware(['web', JwtAuthenticate::class]);
        });
        // Daftarkan observer untuk auto-create folder & cascade delete
        User::observe(UserObserver::class);

        // Paksa auth guard menggunakan JWT jika ada token di cookie
        $this->app->booted(function () {
            config(['auth.defaults.guard' => 'api']);
        });
    }
}
