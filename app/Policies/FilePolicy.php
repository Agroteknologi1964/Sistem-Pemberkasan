<?php

namespace App\Policies;

use App\Models\File;
use App\Models\User;

class FilePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        return null;
    }

    public function view(User $user, File $file): bool
    {
        return $user->canAccessFolder($file->folder);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, File $file): bool
    {
        return false;
    }

    public function delete(User $user, File $file): bool
    {
        return false;
    }
}
