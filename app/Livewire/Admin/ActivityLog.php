<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use Spatie\Activitylog\Models\Activity;

#[Title('Riwayat Aktivitas')] 
class ActivityLog extends Component
{
    use WithPagination;

    public function render()
    {
        // Ambil data log terbaru, urutkan dari yang paling baru
        $logs = Activity::with('causer')->latest()->paginate(15);

        // Hapus pemanggilan ->layout(...)
        return view('livewire.admin.activity-log', compact('logs'));
    }
}