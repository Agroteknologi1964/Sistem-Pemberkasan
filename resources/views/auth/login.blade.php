<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ config('app.name') }}</title>
    <link rel="icon" type="image/jpeg" href="{{ asset('favicon-256.jpg') }}" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' }</script>
    <script src="{{ asset('js/theme.js') }}"></script>
</head>
<body class="flex min-h-screen items-center justify-center bg-gray-50 dark:bg-slate-900/60">
    <div class="w-full max-w-md space-y-8 px-6">

        {{-- Logo / Header --}}
        <div class="text-center">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-600">
                <svg class="h-7 w-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                </svg>
            </div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-slate-100">{{ config('app.name') }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">Masuk untuk mengakses dokumen Anda</p>
        </div>

        {{-- Form --}}
        <div class="rounded-2xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-8 shadow-sm">
            <form method="POST" action="{{ route('login.post') }}" class="space-y-5" >
                @csrf

                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-slate-200">
                        Alamat Email
                    </label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        autocomplete="email"
                        required
                        value="{{ old('email') }}"
                        class="block w-full rounded-lg border border-gray-300 dark:border-slate-700 px-3.5 py-2.5 text-sm
                               placeholder-gray-400 shadow-sm
                               focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200
                               @error('email') border-red-400 focus:border-red-400 focus:ring-red-200 @enderror"
                        placeholder="nama@contoh.com"
                    />
                    @error('email')
                        <p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-slate-200">
                        Password
                    </label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        required
                        class="block w-full rounded-lg border border-gray-300 dark:border-slate-700 px-3.5 py-2.5 text-sm
                               placeholder-gray-400 shadow-sm
                               focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                        placeholder="••••••••"
                    />
                </div>

                <button
                    type="submit"
                    class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white
                           shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2
                           transition-colors duration-150"
                >
                    Masuk
                </button>
            </form>
        </div>

        <p class="text-center text-xs text-gray-400 dark:text-slate-500">
            Sistem ini hanya untuk pengguna terdaftar.<br>Hubungi administrator jika mengalami kendala.
        </p>
    </div>
</body>
</html>
