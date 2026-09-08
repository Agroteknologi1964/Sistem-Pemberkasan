<?php

namespace App\Providers;

use App\Models\File;
use App\Models\Folder;
use App\Policies\FilePolicy;
use App\Policies\FolderPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Folder::class => FolderPolicy::class,
        File::class   => FilePolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
