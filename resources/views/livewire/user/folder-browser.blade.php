<div class="space-y-4">

    {{-- Breadcrumb --}}
    @if(!empty($breadcrumbs))
    <nav class="flex items-center gap-1.5 text-sm text-gray-500 dark:text-slate-400 flex-wrap">
        @foreach($breadcrumbs as $crumb)
            @if(!$loop->last)
                <button wire:click="navigateTo('{{ $crumb['id'] }}')"
                        class="hover:text-indigo-600 font-medium">{{ $crumb['name'] }}</button>
                <span>/</span>
            @else
                <span class="text-gray-900 dark:text-slate-100 font-semibold">{{ $crumb['name'] }}</span>
            @endif
        @endforeach
    </nav>
    @endif

    {{-- Header & Search --}}
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-3 shadow-sm">
        <div>
            @if($folder && $folder->parent_id)
            <button wire:click="navigateUp"
                    class="flex items-center gap-1.5 rounded-lg border border-gray-300 dark:border-slate-700 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-slate-200 hover:bg-gray-50 dark:hover:bg-slate-800 dark:bg-slate-900/60">
                ← Kembali ke folder sebelumnya
            </button>
            @else
            <span class="text-sm font-medium text-gray-500 dark:text-slate-400">Direktori Dokumen</span>
            @endif
        </div>

        {{-- Search Input --}}
        <div class="relative w-full sm:w-64">
            <input wire:model.live.debounce.300ms="search"
                   type="text"
                   placeholder="Cari file atau folder..."
                   class="w-full rounded-lg border border-gray-300 dark:border-slate-700 pl-9 pr-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-gray-400 dark:text-slate-500">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
        </div>
    </div>

    {{-- Grid isi folder --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">

        @if($folder)
            {{-- Subfolder --}}
            @foreach($folder->children as $subfolder)
                <button wire:click="navigateTo('{{ $subfolder->id }}')"
                        class="rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 text-left shadow-sm hover:border-indigo-300 hover:shadow-md transition">
                    <div class="mb-2 text-3xl">📁</div>
                    <p class="truncate text-sm font-medium text-gray-900 dark:text-slate-100">{{ $subfolder->name }}</p>
                </button>
            @endforeach

            {{-- File PDF --}}
            @foreach($folder->files as $file)
                <button wire:click="openPreview('{{ $file->id }}')"
                        class="rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 text-left shadow-sm hover:border-indigo-300 hover:shadow-md transition group">
                    <div class="mb-2 text-red-500">
                        <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                    </div>
                    <p class="truncate text-sm font-medium text-gray-900 dark:text-slate-100">{{ $file->name }}</p>
                    <p class="text-xs text-gray-400 dark:text-slate-500 mt-0.5">{{ $file->humanSize() }}</p>
                    <p class="mt-2 text-xs text-indigo-600 opacity-0 group-hover:opacity-100 transition">
                        Klik untuk preview →
                    </p>
                </button>
            @endforeach

            @if($folder->children->isEmpty() && $folder->files->isEmpty())
                <div class="col-span-full py-12 text-center text-gray-400 dark:text-slate-500">
                    <p class="text-4xl mb-2">📂</p>
                    <p class="text-sm">{{ $search ? 'Tidak ada file atau subfolder yang sesuai dengan pencarian.' : 'Folder ini belum berisi file apapun.' }}</p>
                </div>
            @endif
        @endif
    </div>

    {{-- Preview PDF Modal --}}
    @if($showPreview && $previewFile)
    <div class="fixed inset-0 z-50 flex flex-col bg-black/90 p-4">
        {{-- Header preview --}}
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-white font-medium text-sm truncate max-w-xs">
                📄 {{ $previewFile->name }}
            </h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('user.files.download', $previewFile->id) }}"
                   class="rounded-lg bg-white dark:bg-slate-900/10 px-3 py-1.5 text-xs font-medium text-white hover:bg-white dark:bg-slate-900/20">
                    ⬇ Download
                </a>
                <button wire:click="closePreview"
                        class="rounded-lg bg-white dark:bg-slate-900/10 px-3 py-1.5 text-xs font-medium text-white hover:bg-white dark:bg-slate-900/20">
                    ✕ Tutup
                </button>
            </div>
        </div>

        {{-- PDF iframe --}}
        <div class="flex-1 rounded-xl overflow-hidden">
            <iframe
                src="{{ route('user.files.preview', $previewFile->id) }}"
                class="w-full h-full"
                type="application/pdf"
                title="{{ $previewFile->name }}"
            ></iframe>
        </div>
    </div>
    @endif
</div>
