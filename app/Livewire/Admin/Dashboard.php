<?php

namespace App\Livewire\Admin;

use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Livewire\Component;
use Livewire\Attributes\Title;

#[Title('Dashboard Admin')]
class Dashboard extends Component
{
    public function render()
    {
        $stats = [
            'total_users'   => User::where('role', 'user')->count(),
            'active_users'  => User::where('role', 'user')->where('is_active', true)->count(),
            'total_folders' => Folder::count(),
            'total_files'   => File::count(),
            'storage_used'  => $this->getStorageUsed(),
        ];

        $recentFiles = File::with(['folder', 'uploader'])
            ->latest()
            ->take(5)
            ->get();

        $recentUsers = User::where('role', 'user')
            ->with('rootFolder')
            ->latest()
            ->take(5)
            ->get();

        return view('livewire.admin.dashboard', compact('stats', 'recentFiles', 'recentUsers'));
    }

    private function getStorageUsed(): string
    {
        $bytes = File::sum('size');
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $bytes >= 1024 && $i < 3; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 1) . ' ' . $units[$i];
    }
}
