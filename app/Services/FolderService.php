<?php

namespace App\Services;

use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FolderService
{
    /**
     * Buat folder baru di dalam parent folder.
     */
    public function create(string $name, ?string $parentId = null, ?string $userId = null): Folder
    {
        return Folder::create([
            'name'      => $name,
            'parent_id' => $parentId,
            'user_id'   => $userId,
            'is_root'   => false,
        ]);
    }

    /**
     * Rename folder (hanya admin).
     */
    public function rename(Folder $folder, string $newName): Folder
    {
        $folder->update(['name' => $newName]);
        return $folder->fresh();
    }

    /**
     * Soft delete folder (subfolder dan file di-soft delete secara logis).
     */
    public function delete(Folder $folder): bool
    {
        return DB::transaction(function () use ($folder) {
            return (bool) $folder->delete();
        });
    }

    /**
     * Hapus permanen folder beserta seluruh file fisiknya dari storage.
     */
    public function forceDelete(Folder $folder): bool
    {
        return DB::transaction(function () use ($folder) {
            $folder->deleteFilesRecursively();
            return (bool) $folder->forceDelete();
        });
    }

    /**
     * Assign akses folder ke user (pivot table).
     */
    public function assignToUser(Folder $folder, User $user): void
    {
        $user->accessibleFolders()->syncWithoutDetaching([$folder->id]);
    }

    /**
     * Cabut akses folder dari user.
     */
    public function revokeFromUser(Folder $folder, User $user): void
    {
        $user->accessibleFolders()->detach($folder->id);
    }

    /**
     * Ambil tree folder untuk admin (semua folder rekursif).
     */
    public function getAdminTree(): \Illuminate\Database\Eloquent\Collection
    {
        return Folder::whereNull('parent_id')
            ->with(['allChildren.files', 'files'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Ambil subfolder yang accessible oleh user tertentu.
     */
    public function getUserAccessibleFolders(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return $user->accessibleFolders()
            ->with(['children', 'files'])
            ->get();
    }

    /**
     * Ambil breadcrumb folder dari root ke folder saat ini.
     */
    public function getBreadcrumbs(Folder $folder): array
    {
        return $folder->breadcrumbs();
    }

    /**
     * Pindahkan folder ke parent baru.
     */
    public function move(Folder $folder, ?string $newParentId): Folder
    {
        // Validasi: tidak boleh pindah ke dirinya sendiri atau descendant-nya
        if ($newParentId && $this->isDescendantOf($newParentId, $folder->id)) {
            throw new \InvalidArgumentException('Tidak dapat memindahkan folder ke dalam folder turunannya sendiri.');
        }

        $folder->update(['parent_id' => $newParentId]);
        return $folder->fresh();
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    private function isDescendantOf(string $targetId, string $ancestorId): bool
    {
        $folder = Folder::find($targetId);

        while ($folder && $folder->parent_id) {
            if ($folder->parent_id === $ancestorId) {
                return true;
            }
            $folder = $folder->parent;
        }

        return false;
    }
}
