<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>404 — Halaman Tidak Ditemukan</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex min-h-screen items-center justify-center bg-gray-50">
    <div class="text-center px-6">
        <div class="text-6xl mb-4">📂</div>
        <h1 class="text-2xl font-semibold text-gray-900 mb-2">Halaman Tidak Ditemukan</h1>
        <p class="text-gray-500 mb-6 max-w-sm mx-auto">
            Halaman, folder, atau file yang Anda cari tidak ada atau telah dihapus.
        </p>
        <a href="{{ url()->previous() }}"
           class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
            ← Kembali
        </a>
    </div>
</body>
</html>
