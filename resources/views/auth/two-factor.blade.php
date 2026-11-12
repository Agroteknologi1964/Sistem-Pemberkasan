<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Verifikasi Dua Langkah — {{ config('app.name') }}</title>
    <link rel="icon" type="image/jpeg" href="{{ asset('favicon-256.jpg') }}" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' }</script>
    <script src="{{ asset('js/theme.js') }}"></script>
</head>
<body class="flex min-h-screen items-center justify-center bg-gray-50 dark:bg-slate-900/60">
    <div class="w-full max-w-md space-y-6 px-6">

        <div class="text-center">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-600">
                <svg class="h-7 w-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-slate-100">Verifikasi Dua Langkah</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">
                Masukkan 6 digit dari aplikasi authenticator Anda
            </p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-8 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <form method="POST" action="{{ route('two-factor.verify') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="code" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-slate-200">
                        Kode Verifikasi
                    </label>
                    <input
                        id="code"
                        name="code"
                        type="text"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        autofocus
                        maxlength="20"
                        required
                        class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-center text-lg font-mono tracking-[0.4em]
                               placeholder-gray-400 shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100
                               focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200
                               @error('code') border-red-400 focus:border-red-400 focus:ring-red-200 @enderror"
                        placeholder="000000"
                    />
                    @error('code')
                        <p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <button
                    type="submit"
                    class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white
                           shadow-sm transition-colors hover:bg-indigo-700 focus:outline-none
                           focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    Verifikasi
                </button>
            </form>

            <p class="mt-5 border-t border-gray-100 pt-4 text-center text-xs text-gray-500 dark:border-slate-800 dark:text-slate-400">
                Kehilangan akses ke aplikasi authenticator?
                Masukkan salah satu <strong>kode pemulihan</strong> yang Anda simpan saat mendaftar.
            </p>
        </div>

        <p class="text-center text-xs text-gray-400">
            <a href="{{ route('login') }}" class="underline hover:text-indigo-600">Kembali ke halaman masuk</a>
        </p>
    </div>
</body>
</html>
