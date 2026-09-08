<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class UserManager extends Component
{
    use WithPagination;

    // ─── Form tambah/edit user ───────────────────────────────────────────────

    public bool $showModal = false;
    public bool $isEditing = false;
    public ?string $editingId = null;

    #[Rule('required|string|min:3|max:100')]
    public string $name = '';

    #[Rule('required|email|max:255')]
    public string $email = '';

    #[Rule('nullable|string|min:8|max:255')]
    public ?string $password = null;

    public bool $showDeleteConfirm = false;
    public ?string $deletingId = null;
    public string $search = '';

    // ─── Lifecycle ───────────────────────────────────────────────────────────

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    // ─── Actions ─────────────────────────────────────────────────────────────

    public function openCreateModal(): void
    {
        $this->reset(['name', 'email', 'password', 'isEditing', 'editingId']);
        $this->showModal = true;
    }

    public function openEditModal(string $userId): void
    {
        $user = User::findOrFail($userId);
        $this->editingId  = $userId;
        $this->name       = $user->name;
        $this->email      = $user->email;
        $this->password   = null;
        $this->isEditing  = true;
        $this->showModal  = true;
    }

    public function save(): void
    {
        $rules = [
            'name'  => 'required|string|min:3|max:100',
            'email' => 'required|email|max:255|unique:users,email' . ($this->isEditing ? ',' . $this->editingId : ''),
        ];

        if (! $this->isEditing) {
            $rules['password'] = 'required|string|min:8|max:255';
        }

        $this->validate($rules);

        DB::transaction(function () {
            if ($this->isEditing) {
                $user = User::findOrFail($this->editingId);
                $data = ['name' => $this->name, 'email' => $this->email];

                if ($this->password) {
                    $data['password']              = Hash::make($this->password);
                    $data['force_change_password'] = true;
                }

                $user->update($data);
            } else {
                // Buat user baru — Observer akan auto-create root folder
                User::create([
                    'name'                  => $this->name,
                    'email'                 => $this->email,
                    'password'              => Hash::make($this->password),
                    'role'                  => 'user',
                    'force_change_password' => true,
                ]);
            }
        });

        $this->showModal = false;
        $this->reset(['name', 'email', 'password', 'isEditing', 'editingId']);
        $this->dispatch('notify', message: $this->isEditing ? 'User berhasil diperbarui.' : 'User berhasil ditambahkan.');
    }

    public function confirmDelete(string $userId): void
    {
        $this->deletingId      = $userId;
        $this->showDeleteConfirm = true;
    }

    public function deleteUser(): void
    {
        $user = User::findOrFail($this->deletingId);

        // Observer akan handle cascade delete folder + file
        if ($user) {
        // 1. Hapus folder terkait di database secara permanen
            if ($user->rootFolder) {
                $user->rootFolder->forceDelete(); 
            }

        // 2. Hapus user secara permanen dari database
            $user->forceDelete();

        // 3. (Opsional) Tampilkan pesan sukses
            $this->dispatch('notify', message: 'User berhasil dihapus permanen dari database!');
        }

        $this->showDeleteConfirm = false;
        $this->deletingId        = null;
        $this->dispatch('notify', message: 'User dan semua foldernya berhasil dihapus.');
    }

    public function toggleActive(string $userId): void
    {
        $user = User::findOrFail($userId);
        $user->update(['is_active' => ! $user->is_active]);
    }

    // ─── Render ──────────────────────────────────────────────────────────────

    public function render()
    {
        $users = User::where('role', 'user')
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%');
            }))
            ->with('rootFolder')
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.admin.user-manager', compact('users'));
    }
}
