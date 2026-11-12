<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <link rel="icon" type="image/jpeg" href="{{ asset('favicon-256.jpg') }}" />
    <title>{{ config('app.name', 'Sistem Pemberkasan') }}@hasSection('title') — @yield('title')@endif</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: { colors: { primary: { 50:'#eef2ff',100:'#e0e7ff',500:'#6366f1',600:'#4f46e5',700:'#4338ca' } } } }
        }
    </script>
    {{-- Pemasangan class `dark` harus SEBELUM paint agar tidak berkedip. --}}
    <script src="{{ asset('js/theme.js') }}"></script>

    {{-- @vite(['resources/css/app.css', 'resources/js/app.js']) --}}
</head>
<body class="h-full bg-gray-50 font-sans antialiased dark:bg-slate-950 dark:text-slate-100">

    {{-- Navbar --}}
    <nav class="sticky top-0 z-40 border-b border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="mx-auto flex h-14 max-w-7xl items-center justify-between px-4 sm:px-6">
            <div class="flex items-center gap-3">
                <svg class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                </svg>
                <a href="{{ auth()->check() && auth()->user()->isAdmin() ? route('admin.dashboard') : '#' }}"
                   class="text-base font-semibold text-gray-900 hover:text-indigo-600 transition-colors dark:text-slate-100 dark:hover:text-indigo-400">
                    {{ config('app.name', 'Sistem Pemberkasan') }}
                </a>
            </div>

            <div class="flex items-center gap-4 text-sm">
                {{-- Toggle tema --}}
                <button type="button"
                        id="theme-toggle"
                        aria-label="Ganti mode terang/gelap"
                        title="Ganti mode terang/gelap"
                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-gray-300 text-gray-600 transition-colors hover:bg-gray-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    <svg class="theme-icon-dark h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
                    </svg>
                    <svg class="theme-icon-light hidden h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1.5M12 19.5V21M4.22 4.22l1.06 1.06M18.72 18.72l1.06 1.06M3 12h1.5M19.5 12H21M4.22 19.78l1.06-1.06M18.72 5.28l1.06-1.06"/>
                        <circle cx="12" cy="12" r="3.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>

                @auth
                    <span class="hidden sm:inline text-gray-500 dark:text-slate-400">
                        {{ auth()->user()->name }}
                        <span class="ml-1 rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300">
                            {{ ucfirst(auth()->user()->role) }}
                        </span>
                    </span>

                    @if(auth()->user()->isUser())
                        <a href="{{ route('user.profile.password') }}"
                           class="text-sm text-gray-600 hover:text-indigo-600 transition-colors dark:text-slate-300 dark:hover:text-indigo-400">
                            Ganti Password
                        </a>
                        <a href="{{ route('user.profile.security') }}"
                           class="text-sm text-gray-600 hover:text-indigo-600 transition-colors dark:text-slate-300 dark:hover:text-indigo-400">
                            Keamanan
                        </a>
                    @else
                        <a href="{{ route('admin.profile.password') }}"
                           class="text-sm text-gray-600 hover:text-indigo-600 transition-colors dark:text-slate-300 dark:hover:text-indigo-400">
                            Ganti Password
                        </a>
                        <a href="{{ route('admin.profile.security') }}"
                           class="text-sm text-gray-600 hover:text-indigo-600 transition-colors dark:text-slate-300 dark:hover:text-indigo-400">
                            Keamanan
                        </a>
                    @endif

                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit"
                                class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                            Keluar
                        </button>
                    </form>
                @endauth
            </div>
        </div>
    </nav>

    <div class="mx-auto flex max-w-7xl">

        {{-- Sidebar Admin --}}
        @auth
        @if(auth()->user()->isAdmin())
        <aside class="sticky top-14 h-[calc(100vh-3.5rem)] w-56 shrink-0 overflow-y-auto border-r border-gray-200 bg-white py-4 px-3 dark:border-slate-800 dark:bg-slate-900">
            <nav class="space-y-0.5">
                <a href="{{ route('admin.dashboard') }}"
                   class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors
                          {{ request()->routeIs('admin.dashboard') ? 'bg-indigo-50 dark:bg-indigo-500/15 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300' : 'text-gray-700 hover:bg-gray-50 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                    </svg>
                    Dashboard
                </a>
                <a href="{{ route('admin.folders.index') }}"
                   class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors
                          {{ request()->routeIs('admin.folders*') ? 'bg-indigo-50 dark:bg-indigo-500/15 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300' : 'text-gray-700 hover:bg-gray-50 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                    </svg>
                    Folder & File
                </a>
                <a href="{{ route('admin.users.index') }}"
                   class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors
                          {{ request()->routeIs('admin.users*') ? 'bg-indigo-50 dark:bg-indigo-500/15 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300' : 'text-gray-700 hover:bg-gray-50 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
                    </svg>
                    Manajemen User
                </a>
                <a href="{{ route('admin.logs.index') }}"
                    class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors
                        {{ request()->routeIs('admin.logs*') ? 'bg-indigo-50 dark:bg-indigo-500/15 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300' : 'text-gray-700 hover:bg-gray-50 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                    {{-- Icon Clock / History --}}
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Riwayat Aktivitas
                </a>
            </nav>
        </aside>
        @endif
        @endauth

        {{-- Konten Utama --}}
        <main class="min-h-[calc(100vh-3.5rem)] flex-1 p-6">

            {{-- Toast Notification --}}
            <div
                x-data="{ show: false, message: '' }"
                x-on:notify.window="message = $event.detail.message; show = true; setTimeout(() => show = false, 3500)"
                x-show="show"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed bottom-6 right-6 z-50 flex items-center gap-2 rounded-xl bg-gray-900 px-4 py-3 text-sm font-medium text-white shadow-xl dark:border dark:border-slate-700 dark:bg-slate-800"
                style="display: none;"
            >
                <svg class="h-4 w-4 text-green-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
                <span x-text="message"></span>
            </div>

            @yield('content')
            {{ $slot }}
        </main>
    </div>

    @livewireScripts

    <!-- {{-- Alpine.js CDN (hapus jika pakai Vite) --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script> -->

    {{-- JWT Auto-refresh setiap 20 menit --}}
    <script>
        setInterval(async () => {
            try {
                await fetch('/auth/refresh', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
                });
            } catch(e) {}
        }, 20 * 60 * 1000);
    </script>

    {{-- Dark mode toggle: ikon mengikuti class `dark` di <html> --}}
    <script>
        (function () {
            const btn   = document.getElementById('theme-toggle');
            const iconD = btn.querySelector('.theme-icon-dark');
            const iconL = btn.querySelector('.theme-icon-light');

            function syncIcons() {
                const dark = document.documentElement.classList.contains('dark');
                // Mode terang -> tampilkan ikon bulan (aksi: jadi gelap)
                iconD.classList.toggle('hidden', dark);
                iconL.classList.toggle('hidden', !dark);
            }

            btn.addEventListener('click', function () {
                window.theme.toggle();
                syncIcons();
            });

            syncIcons();
        })();
    </script>
</body>
</html>
