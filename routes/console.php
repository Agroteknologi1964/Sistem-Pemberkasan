<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Bersihkan JWT blacklist yang sudah kadaluarsa setiap hari
Schedule::command('jwt:purge-blacklist')->daily();

// Bersihkan soft-deleted records lebih dari 30 hari
Schedule::call(function () {
    \App\Models\File::onlyTrashed()->where('deleted_at', '<', now()->subDays(30))->forceDelete();
    \App\Models\Folder::onlyTrashed()->where('deleted_at', '<', now()->subDays(30))->forceDelete();
    \App\Models\User::onlyTrashed()->where('deleted_at', '<', now()->subDays(30))->forceDelete();
})->weekly()->sundays()->at('02:00');
