<?php

namespace App\Livewire\Admin;

use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use App\Services\FileService;
use App\Services\FolderService;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class FolderManager extends Component
{
    use WithFileUploads;

    public ?string $currentFolderId = null;
    public ?Folder $currentFolder   = null;

    // Modal states
    public bool $showFolderModal = false;
    public bool $showRenameModal = false;
    public bool $showUploadModal = false;
    public bool $showDeleteConfirm = false;
    public bool $showAssignModal = false;

    // Folder form
    public string $folderName = '';
    public bool $isEditingFolder = false;
    public ?string $editingFolderId = null;

    // Rename form
    public string $renameName      = '';
    public ?string $renameTargetId = null;
    public string $renameType      = 'folder'; // 'folder' | 'file'

    // Upload form
    #[Rule('required|file|mimes:pdf|max:20480')]
    public $uploadFile = null;
    public string $uploadName = '';

    // Delete confirm
    public ?string $deleteTargetId = null;
    public string $deleteType      = 'folder';

    // Assign user
    public ?string $assignFolderId = null;
    public array $selectedUserIds  = [];

    // Search filter
    public string $search = '';

    // ─── Lifecycle ───────────────────────────────────────────────────────────

    public function mount(?string $folderId = null): void
    {
        $this->currentFolderId = $folderId;
        $this->loadCurrentFolder();
    }

    public function updatedSearch(): void
    {
        $this->loadCurrentFolder();
    }

    public function loadCurrentFolder(): void
    {
        if ($this->currentFolderId) {
            $this->currentFolder = Folder::with([
                'children' => fn ($q) => $q->when($this->search, fn ($sq) => $sq->where('name', 'like', "%{$this->search}%")),
                'files'    => fn ($q) => $q->when($this->search, fn ($sq) => $sq->where('name', 'like', "%{$this->search}%")),
                'parent'
            ])->findOrFail($this->currentFolderId);
        }
    }

    // ─── Navigasi ────────────────────────────────────────────────────────────

    public function navigateTo(?string $folderId = null): void
    {
        $this->currentFolderId = $folderId;
        $this->currentFolder   = null;
        $this->search          = '';
        $this->loadCurrentFolder();
    }

    public function navigateUp(): void
    {
        $this->search = '';
        if ($this->currentFolder && $this->currentFolder->parent_id) {
            $this->currentFolderId = $this->currentFolder->parent_id;
            $this->loadCurrentFolder();
        } else {
            $this->currentFolderId = null;
            $this->currentFolder   = null;
        }
    }

    // ─── Folder Actions ──────────────────────────────────────────────────────

    public function openCreateFolderModal(): void
    {
        $this->reset(['folderName', 'isEditingFolder', 'editingFolderId']);
        $this->showFolderModal = true;
    }

    public function saveFolder(): void
    {
        $this->validate(['folderName' => 'required|string|max:100']);

        $service = app(FolderService::class);

        if ($this->isEditingFolder) {
            $folder = Folder::findOrFail($this->editingFolderId);
            $service->rename($folder, $this->folderName);
        } else {
            $service->create($this->folderName, $this->currentFolderId);
        }

        $this->showFolderModal = false;
        $this->loadCurrentFolder();
        $this->dispatch('notify', message: 'Folder berhasil ' . ($this->isEditingFolder ? 'diperbarui.' : 'dibuat.'));
    }

    // ─── Rename ──────────────────────────────────────────────────────────────

    public function openRename(string $targetId, string $type = 'folder'): void
    {
        $this->renameTargetId = $targetId;
        $this->renameType     = $type;
        $this->renameName     = $type === 'folder'
            ? Folder::findOrFail($targetId)->name
            : File::findOrFail($targetId)->name;
        $this->showRenameModal = true;
    }

    public function saveRename(): void
    {
        $this->validate(['renameName' => 'required|string|max:100']);

        if ($this->renameType === 'folder') {
            $folder = Folder::findOrFail($this->renameTargetId);
            app(FolderService::class)->rename($folder, $this->renameName);
        } else {
            $file = File::findOrFail($this->renameTargetId);
            app(FileService::class)->rename($file, $this->renameName);
        }

        $this->showRenameModal = false;
        $this->loadCurrentFolder();
        $this->dispatch('notify', message: 'Nama berhasil diperbarui.');
    }

    // ─── Upload ──────────────────────────────────────────────────────────────

    public function openUploadModal(): void
    {
        $this->reset(['uploadFile', 'uploadName']);
        $this->showUploadModal = true;
    }

    public function saveUpload(): void
    {
        $this->validate([
            'uploadFile' => 'required|file|mimes:pdf|max:20480',
        ]);

        if (! $this->currentFolder) {
            $this->addError('uploadFile', 'Pilih folder tujuan terlebih dahulu.');
            return;
        }

        $file = app(FileService::class)->upload(
            $this->uploadFile->getRealPath()
                ? $this->uploadFile
                : throw new \RuntimeException('File tidak valid.'),
            $this->currentFolder,
            auth()->user()
        );

        if ($this->uploadName) {
            app(FileService::class)->rename($file, $this->uploadName);
        }

        $this->showUploadModal = false;
        $this->loadCurrentFolder();
        $this->dispatch('notify', message: 'File berhasil diupload.');
    }

    // ─── Delete ──────────────────────────────────────────────────────────────

    public function confirmDelete(string $targetId, string $type = 'folder'): void
    {
        $this->deleteTargetId   = $targetId;
        $this->deleteType       = $type;
        $this->showDeleteConfirm = true;
    }

    public function deleteCurrent(): void
    {
        if ($this->deleteType === 'folder') {
            $folder = Folder::findOrFail($this->deleteTargetId);
            app(FolderService::class)->delete($folder);
        } else {
            $file = File::findOrFail($this->deleteTargetId);
            app(FileService::class)->delete($file);
        }

        $this->showDeleteConfirm = false;
        $this->loadCurrentFolder();
        $this->dispatch('notify', message: ucfirst($this->deleteType) . ' berhasil dihapus.');
    }

    // ─── Assign User ─────────────────────────────────────────────────────────

    public function openAssignModal(string $folderId): void
    {
        $this->assignFolderId  = $folderId;
        $folder                = Folder::with('accessUsers')->findOrFail($folderId);
        $this->selectedUserIds = $folder->accessUsers->pluck('id')->toArray();
        $this->showAssignModal = true;
    }

    public function saveAssign(): void
    {
        $folder = Folder::findOrFail($this->assignFolderId);
        $folder->accessUsers()->sync($this->selectedUserIds);

        $this->showAssignModal = false;
        $this->dispatch('notify', message: 'Akses folder berhasil diperbarui.');
    }

    // ─── Render ──────────────────────────────────────────────────────────────

    public function render()
    {
        // Root folders saat tidak ada currentFolder
        $rootFolders = is_null($this->currentFolderId)
            ? Folder::whereNull('parent_id')
                ->when($this->search, fn ($q) => $q->where('name', 'like', '%' . $this->search . '%'))
                ->withCount(['children', 'files'])
                ->orderBy('name')
                ->get()
            : collect();

        $users = User::where('role', 'user')->orderBy('name')->get();

        return view('livewire.admin.folder-manager', compact('rootFolders', 'users'));
    }
}
