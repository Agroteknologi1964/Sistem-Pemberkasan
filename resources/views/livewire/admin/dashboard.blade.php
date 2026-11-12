<div class="space-y-6">

    <div>
        <h1 class="text-lg font-semibold text-gray-900 dark:text-slate-100">Dashboard Admin</h1>
        <p class="text-sm text-gray-500 dark:text-slate-400">Ringkasan sistem pemberkasan.</p>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wide">Total User</p>
            <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-slate-100">{{ $stats['total_users'] }}</p>
            <p class="text-xs text-green-600 mt-1">{{ $stats['active_users'] }} aktif</p>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wide">Total Folder</p>
            <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-slate-100">{{ $stats['total_folders'] }}</p>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wide">Total File PDF</p>
            <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-slate-100">{{ $stats['total_files'] }}</p>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wide">Storage Digunakan</p>
            <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-slate-100">{{ $stats['storage_used'] }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

        {{-- File Terbaru --}}
        <div class="rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
            <div class="border-b border-gray-100 dark:border-slate-800 px-4 py-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-slate-100">File Terbaru Diupload</h2>
                <a href="{{ route('admin.folders.index') }}" class="text-xs text-indigo-600 hover:underline">Lihat semua →</a>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($recentFiles as $file)
                    <div class="flex items-center gap-3 px-4 py-3">
                        <span class="text-xl">📄</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-900 dark:text-slate-100">{{ $file->name }}</p>
                            <p class="text-xs text-gray-500 dark:text-slate-400">
                                {{ $file->folder->name ?? '—' }} · {{ $file->humanSize() }} · {{ $file->created_at->diffForHumans() }}
                            </p>
                        </div>
                        <a href="{{ route('admin.files.preview', $file->id) }}" target="_blank"
                           class="shrink-0 text-xs text-indigo-600 hover:underline">Preview</a>
                    </div>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-gray-400 dark:text-slate-500">Belum ada file.</div>
                @endforelse
            </div>
        </div>

        {{-- User Terbaru --}}
        <div class="rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
            <div class="border-b border-gray-100 dark:border-slate-800 px-4 py-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-slate-100">User Terbaru Ditambahkan</h2>
                <a href="{{ route('admin.users.index') }}" class="text-xs text-indigo-600 hover:underline">Lihat semua →</a>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($recentUsers as $user)
                    <div class="flex items-center gap-3 px-4 py-3">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-bold text-indigo-700">
                            {{ strtoupper(substr($user->name, 0, 1)) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-900 dark:text-slate-100">{{ $user->name }}</p>
                            <p class="text-xs text-gray-500 dark:text-slate-400">{{ $user->email }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium
                                {{ $user->is_active ? 'bg-green-100 text-green-700 dark:text-emerald-300' : 'bg-red-100 text-red-700 dark:text-red-300' }}">
                                {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                            @if($user->rootFolder)
                                <br>
                                <a href="{{ route('admin.folders.show', $user->rootFolder->id) }}"
                                   class="text-xs text-indigo-600 hover:underline">Buka folder</a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-gray-400 dark:text-slate-500">Belum ada user.</div>
                @endforelse
            </div>
        </div>

    </div>
</div>
