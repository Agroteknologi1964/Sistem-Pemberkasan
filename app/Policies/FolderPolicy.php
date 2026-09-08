<?php

namespace App\Policies;

use App\Models\Folder;
use App\Models\User;

class FolderPolicy
{
    /**
     * Admin bisa melakukan semua aksi.
     * User hanya bisa view folder yang diakses.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true; // Admin bypass semua policy
        }

        return null; // Lanjut ke method masing-masing
    }

    public function view(User $user, Folder $folder): bool
    {
        return $user->canAccessFolder($folder);
    }

    public function create(User $user): bool
    {
        return false; // Hanya admin (sudah handle di before())
    }

    public function update(User $user, Folder $folder): bool
    {
        return false;
    }

    public function delete(User $user, Folder $folder): bool
    {
        return false;
    }
}
