<?php

namespace App\Livewire\User;

use App\Models\File;
use App\Models\Folder;
use App\Services\FileService;
use Livewire\Component;

class FolderBrowser extends Component
{
    public string $folderId;
    public ?Folder $folder = null;
    public ?File $previewFile = null;
    public bool $showPreview = false;
    public string $search = '';

    // ─── Lifecycle ───────────────────────────────────────────────────────────

    public function mount(string $folderId): void
    {
        $this->folderId = $folderId;
        $this->loadFolder();
    }

    public function updatedSearch(): void
    {
        $this->loadFolder();
    }

    public function loadFolder(): void
    {
        $folder = Folder::with([
            'children' => fn ($q) => $q->when($this->search, fn ($sq) => $sq->where('name', 'like', "%{$this->search}%")),
            'files'    => fn ($q) => $q->when($this->search, fn ($sq) => $sq->where('name', 'like', "%{$this->search}%")),
            'parent'
        ])->findOrFail($this->folderId);

        // Pastikan user punya akses ke folder ini
        if (! auth()->user()->canAccessFolder($folder)) {
            abort(403, 'Anda tidak memiliki akses ke folder ini.');
        }

        $this->folder = $folder;
    }

    // ─── Navigasi ────────────────────────────────────────────────────────────

    public function navigateTo(string $folderId): void
    {
        $folder = Folder::findOrFail($folderId);

        // User hanya bisa browse subfolder dari folder yang diakses
        if (! auth()->user()->canAccessFolder($this->getAncestorAccessFolder($folder))) {
            abort(403);
        }

        $this->folderId    = $folderId;
        $this->search      = '';
        $this->showPreview = false;
        $this->loadFolder();
    }

    public function navigateUp(): void
    {
        if ($this->folder && $this->folder->parent_id) {
            $this->folderId = $this->folder->parent_id;
            $this->search   = '';
            $this->loadFolder();
        }
    }

    // ─── Preview PDF ──────────────────────────────────────────────────────────

    public function openPreview(string $fileId): void
    {
        $file = File::findOrFail($fileId);

        // Pastikan file ada di folder yang diakses user
        if (! auth()->user()->canAccessFolder($file->folder)) {
            abort(403);
        }

        $this->previewFile = $file;
        $this->showPreview = true;
    }

    public function closePreview(): void
    {
        $this->showPreview = false;
        $this->previewFile = null;
    }

    // ─── Render ──────────────────────────────────────────────────────────────

    public function render()
    {
        $breadcrumbs = $this->folder ? $this->folder->breadcrumbs() : [];

        return view('livewire.user.folder-browser', compact('breadcrumbs'));
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    /**
     * Ambil ancestor folder yang di-assign langsung ke user (untuk cek akses).
     */
    private function getAncestorAccessFolder(Folder $folder): Folder
    {
        $accessibleIds = auth()->user()
            ->accessibleFolders()
            ->pluck('folders.id')
            ->toArray();

        $current = $folder;
        while ($current) {
            if (in_array($current->id, $accessibleIds)) {
                return $current;
            }
            $current = $current->parent;
        }

        return $folder; // fallback (akan gagal di canAccessFolder)
    }
}
