<div x-data class="space-y-4">

    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-1.5 text-sm text-gray-500">
        <button wire:click="navigateTo(null)" class="hover:text-indigo-600 font-medium">Semua Folder</button>
        @if($currentFolder)
            @foreach($currentFolder->breadcrumbs() as $crumb)
                <span>/</span>
                <button wire:click="navigateTo('{{ $crumb['id'] }}')"
                        class="hover:text-indigo-600 {{ $loop->last ? 'text-gray-900 font-medium' : '' }}">
                    {{ $crumb['name'] }}
                </button>
            @endforeach
        @endif
    </nav>

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white p-3 shadow-sm">
        <div class="flex flex-wrap items-center gap-2">
            @if($currentFolder)
                <button wire:click="navigateUp"
                        class="flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    ←
                </button>
            @endif

            <button wire:click="openCreateFolderModal"
                    class="flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                <span>+ Folder Baru</span>
            </button>

            @if($currentFolder)
                <button wire:click="openUploadModal"
                        class="flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    ↑ Upload PDF
                </button>
            @endif
        </div>

        {{-- Search Input --}}
        <div class="relative w-full sm:w-64">
            <input wire:model.live.debounce.300ms="search"
                   type="text"
                   placeholder="Cari folder atau file..."
                   class="w-full rounded-lg border border-gray-300 pl-9 pr-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-gray-400">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
        </div>
    </div>

    {{-- Grid Folder & File --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">

        {{-- Root folders (jika tidak ada current folder) --}}
        @if(!$currentFolder)
            @forelse($rootFolders as $folder)
                <div class="group relative rounded-xl border border-gray-200 bg-white p-4 shadow-sm hover:border-indigo-300 hover:shadow-md transition">
                    <button wire:click="navigateTo('{{ $folder->id }}')" class="block w-full text-left">
                        {{-- Folder Icon --}}
                        <div class="flex items-center space-x-2 p-2 text-slate-700 hover:text-blue-600 group">
                            <svg xmlns="http://w3.org" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-amber-500 group-hover:text-blue-500 transition-colors duration-200">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.75" />
                            </svg>
                        </div>
                        <p class="truncate text-sm font-medium text-gray-900">{{ $folder->name }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">
                            {{ $folder->children_count ?? 0 }} subfolder · {{ $folder->files_count ?? 0 }} file
                        </p>
                    </button>

                    <div class="absolute right-2 top-2 hidden group-hover:flex gap-1 bg-white/80 p-1 rounded-lg backdrop-blur-sm">
        
                        {{-- Tombol Rename --}}
                        <button wire:click="openRename('{{ $folder->id }}', 'folder')" class="rounded p-1.5 text-gray-500 hover:bg-gray-100 hover:text-indigo-600 transition-colors" title="Rename">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                            </svg>
                        </button>
        
                        {{-- Tombol Assign User --}}
                        <button wire:click="openAssignModal('{{ $folder->id }}')" class="rounded p-1.5 text-gray-500 hover:bg-gray-100 hover:text-indigo-600 transition-colors" title="Assign User">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                            </svg>
                        </button>
        
                        {{-- Tombol Hapus --}}
                        <button wire:click="confirmDelete('{{ $folder->id }}', 'folder')"
                                class="rounded p-1.5 text-gray-500 hover:bg-red-50 hover:text-red-600 transition-colors" title="Hapus">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            @empty
                <div class="col-span-full py-12 text-center text-gray-400">
                    <p class="text-4xl mb-2">📂</p>
                    <p class="text-sm">{{ $search ? 'Tidak ada folder yang sesuai dengan pencarian.' : 'Belum ada folder. Folder akan otomatis terbuat saat user ditambahkan.' }}</p>
                </div>
            @endforelse
        @endif

        {{-- Current folder: Subfolder --}}
        @if($currentFolder)
            @foreach($currentFolder->children as $subfolder)
                <div class="group relative rounded-xl border border-gray-200 bg-white p-4 shadow-sm hover:border-indigo-300 hover:shadow-md transition">
                    <button wire:click="navigateTo('{{ $subfolder->id }}')" class="block w-full text-left">
                        {{-- Folder Icon --}}
                        <div class="flex items-center space-x-2 p-2 text-slate-700 hover:text-blue-600 group">
                            <svg xmlns="http://w3.org" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-amber-500 group-hover:text-blue-500 transition-colors duration-200">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.75" />
                            </svg>
                        </div>
                        <p class="truncate text-sm font-medium text-gray-900">{{ $subfolder->name }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">
                            {{ $subfolder->children_count ?? $subfolder->children->count() }} subfolder · {{ $subfolder->files_count ?? $subfolder->files->count() }} file
                        </p>
                    </button>

                    <div class="absolute right-2 top-2 hidden group-hover:flex gap-1 bg-white/80 p-1 rounded-lg backdrop-blur-sm">
        
                        {{-- Tombol Rename --}}
                        <button wire:click="openRename('{{ $subfolder->id }}', 'folder')" class="rounded p-1.5 text-gray-500 hover:bg-gray-100 hover:text-indigo-600 transition-colors" title="Rename">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                            </svg>
                        </button>
        
                        {{-- Tombol Assign User --}}
                        <button wire:click="openAssignModal('{{ $subfolder->id }}')" class="rounded p-1.5 text-gray-500 hover:bg-gray-100 hover:text-indigo-600 transition-colors" title="Assign User">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                            </svg>
                        </button>
        
                        {{-- Tombol Hapus --}}
                        <button wire:click="confirmDelete('{{ $subfolder->id }}', 'folder')"
                                class="rounded p-1.5 text-gray-500 hover:bg-red-50 hover:text-red-600 transition-colors" title="Hapus">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            @endforeach

            {{-- Current folder: File PDF --}}
            @foreach($currentFolder->files as $file)
                <div class="group relative rounded-xl border border-gray-200 bg-white p-4 shadow-sm hover:border-indigo-300 hover:shadow-md transition">
                    <a href="{{ route('admin.files.preview', $file->id) }}" target="_blank" class="block">
                        
                        {{-- Icon File PDF --}}
                        <div class="mb-2 text-red-500">
                            <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                            </svg>
                        </div>

                        <p class="truncate text-sm font-medium text-gray-900">{{ $file->name }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ $file->humanSize() }}</p>
                    </a>
                    
                    {{-- Aksi --}}
                    <div class="absolute right-2 top-2 hidden group-hover:flex gap-1 bg-white/80 p-1 rounded-lg backdrop-blur-sm">
                        
                        {{-- Edit --}}
                        <button wire:click="openRename('{{ $file->id }}', 'file')"
                                class="rounded p-1.5 text-gray-500 hover:bg-gray-100 hover:text-indigo-600 transition-colors" title="Rename">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                            </svg>
                        </button>
                        
                        {{-- Download --}}
                        <a href="{{ route('admin.files.download', $file->id) }}"
                           class="rounded p-1.5 text-gray-500 hover:bg-gray-100 hover:text-indigo-600 transition-colors" title="Download">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                        </a>
                        
                        {{-- Hapus --}}
                        <button wire:click="confirmDelete('{{ $file->id }}', 'file')"
                                class="rounded p-1.5 text-gray-500 hover:bg-red-50 hover:text-red-600 transition-colors" title="Hapus">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>

                    </div>
                </div>
            @endforeach

            {{-- State: Folder Kosong --}}
            @if($currentFolder->children->isEmpty() && $currentFolder->files->isEmpty())
                <div class="col-span-full py-12 text-center text-gray-400">
                    <div class="mx-auto mb-3 flex justify-center text-gray-300">
                        <svg class="h-16 w-16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-gray-500">{{ $search ? 'Tidak ada subfolder atau file yang sesuai dengan pencarian.' : 'Folder ini kosong.' }}</p>
                    @if(!$search)
                    <p class="text-xs mt-1">Tambahkan subfolder atau upload file PDF.</p>
                    @endif
                </div>
            @endif
        @endif
    </div>

    {{-- Modal: Buat/Edit Folder --}}
    @if($showFolderModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            <h2 class="mb-4 text-base font-semibold text-gray-900">
                {{ $isEditingFolder ? 'Rename Folder' : 'Buat Folder Baru' }}
            </h2>
            <input wire:model.defer="folderName" type="text" placeholder="Nama folder..."
                   class="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                   autofocus />
            @error('folderName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            <div class="mt-4 flex justify-end gap-2">
                <button wire:click="$set('showFolderModal', false)"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Batal
                </button>
                <button wire:click="saveFolder"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Simpan
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal: Rename --}}
    @if($showRenameModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            <h2 class="mb-4 text-base font-semibold text-gray-900">Rename {{ ucfirst($renameType) }}</h2>
            <input wire:model.defer="renameName" type="text"
                   class="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                   autofocus />
            @error('renameName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            <div class="mt-4 flex justify-end gap-2">
                <button wire:click="$set('showRenameModal', false)"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Batal
                </button>
                <button wire:click="saveRename"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Simpan
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal: Upload PDF --}}
    @if($showUploadModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            <h2 class="mb-4 text-base font-semibold text-gray-900">Upload File PDF</h2>
            <div class="space-y-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">File PDF</label>
                    <input wire:model="uploadFile" type="file" accept=".pdf"
                           class="block w-full text-sm text-gray-500 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100" />
                    @error('uploadFile') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Nama Tampilan (opsional)</label>
                    <input wire:model.defer="uploadName" type="text" placeholder="Kosongkan untuk pakai nama file asli"
                           class="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200" />
                </div>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button wire:click="$set('showUploadModal', false)"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Batal
                </button>
                <button wire:click="saveUpload"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-70 transition-all">
                    <svg wire:loading wire:target="saveUpload" class="mr-2 h-4 w-4 animate-spin text-white" ...>
                        ...
                    </svg>
                    <span>Upload</span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal: Assign User --}}
    @if($showAssignModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            <h2 class="mb-4 text-base font-semibold text-gray-900">Assign Akses User ke Folder</h2>
            <div class="max-h-60 overflow-y-auto space-y-2">
                @foreach($users as $user)
                    <label class="flex items-center gap-3 rounded-lg border border-gray-200 p-3 hover:bg-gray-50 cursor-pointer">
                        <input type="checkbox"
                               wire:model="selectedUserIds"
                               value="{{ $user->id }}"
                               class="h-4 w-4 rounded border-gray-300 text-indigo-600" />
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $user->name }}</p>
                            <p class="text-xs text-gray-500">{{ $user->email }}</p>
                        </div>
                    </label>
                @endforeach
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button wire:click="$set('showAssignModal', false)"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Batal
                </button>
                <button wire:click="saveAssign"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Simpan Akses
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal: Konfirmasi Hapus --}}
    @if($showDeleteConfirm)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl">
            <div class="mb-4 text-center">
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-red-100">
                    <span class="text-2xl">⚠️</span>
                </div>
                <h2 class="text-base font-semibold text-gray-900">Hapus {{ ucfirst($deleteType) }}?</h2>
                <p class="mt-1 text-sm text-gray-500">
                    @if($deleteType === 'folder')
                        Seluruh subfolder dan file di dalamnya akan ikut terhapus secara permanen.
                    @else
                        File ini akan dihapus secara permanen.
                    @endif
                </p>
            </div>
            <div class="flex gap-2">
                <button wire:click="$set('showDeleteConfirm', false)"
                        class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Batal
                </button>
                <button wire:click="deleteCurrent"
                        class="flex-1 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                    Ya, Hapus
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
