<?php

namespace App\Observers;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class UserObserver
{
    /**
     * Saat user berhasil dibuat, otomatis buat root folder atas namanya.
     */
    public function created(User $user): void
    {
        if ($user->isUser()) {
            $rootFolder = Folder::create([
                'name'    => $user->name,
                'user_id' => $user->id,
                'is_root' => true,
            ]);

            // Assign akses folder root ke user itu sendiri
            $user->accessibleFolders()->attach($rootFolder->id);
        }
    }

    /**
     * Saat nama user diubah, otomatis sinkronkan nama root foldernya.
     */
    public function updated(User $user): void
    {
        if ($user->wasChanged('name')) {
            $rootFolder = $user->rootFolder()->withTrashed()->first();
            if ($rootFolder) {
                $rootFolder->update(['name' => $user->name]);
            }
        }
    }

    /**
     * Sebelum user dihapus permanen (force delete), hapus semua folder + file fisik.
     */
    public function forceDeleted(User $user): void
    {
        // Hapus file fisik secara rekursif dari root folder
        $rootFolder = $user->rootFolder()->withTrashed()->first();

        if ($rootFolder) {
            $this->deleteFilesInFolder($rootFolder);
            $rootFolder->forceDelete();
        }
    }

    /**
     * Saat soft delete, soft delete root folder milik user.
     */
    public function deleted(User $user): void
    {
        if ($user->rootFolder) {
            $user->rootFolder->delete(); // soft delete
        }
    }

    // ─── Private Helpers ─────────────────────────────────────────────────────

    private function deleteFilesInFolder(Folder $folder): void
    {
        $folder->load(['files', 'children']);

        // Hapus file fisik
        foreach ($folder->files as $file) {
            $file->deletePhysicalFile();
        }

        // Rekursif ke subfolder
        foreach ($folder->children as $child) {
            $this->deleteFilesInFolder($child);
        }
    }
}
