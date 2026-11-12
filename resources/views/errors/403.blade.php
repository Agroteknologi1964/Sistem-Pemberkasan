<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>403 — Akses Ditolak</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex min-h-screen items-center justify-center bg-gray-50 dark:bg-slate-900/60">
    <div class="text-center px-6">
        <div class="text-6xl mb-4">🔒</div>
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-slate-100 mb-2">Akses Ditolak</h1>
        <p class="text-gray-500 dark:text-slate-400 mb-6 max-w-sm mx-auto">
            Anda tidak memiliki izin untuk mengakses halaman atau resource ini.
            Hubungi administrator jika Anda merasa ini adalah kesalahan.
        </p>
        <a href="{{ url()->previous() }}"
           class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
            ← Kembali
        </a>
    </div>
</body>
</html>
