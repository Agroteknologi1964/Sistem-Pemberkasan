#!/bin/sh
set -e

echo "[entrypoint] Menunggu database ${DB_HOST:-db}:${DB_PORT:-3306} ..."
i=0
until php -r '
    $h = getenv("DB_HOST") ?: "db";
    $p = getenv("DB_PORT") ?: "3306";
    $s = @fsockopen($h, (int) $p, $e, $m, 2);
    exit($s ? 0 : 1);
' 2>/dev/null; do
    i=$((i + 1))
    if [ "$i" -ge 30 ]; then
        echo "[entrypoint] Database tidak terjangkau setelah 60 detik." >&2
        exit 1
    fi
    sleep 2
done
echo "[entrypoint] Database siap."

# JWT_SECRET wajib ada — kalau kosong, aplikasi crash saat login (lihat error seeding).
php -r '
$env = file_exists(".env") ? file_get_contents(".env") : "";
if (trim((string) getenv("JWT_SECRET")) === "" && ! preg_match("/^JWT_SECRET=\S+/m", $env)) {
    fwrite(STDERR, "[entrypoint] FATAL: JWT_SECRET kosong. Jalankan `php artisan jwt:secret` lalu isi JWT_SECRET di .env\n");
    exit(1);
}
'

# Tunggu APP_KEY juga — tanpa ini session/cookie tidak bisa dienkripsi.
php -r '
$env = file_exists(".env") ? file_get_contents(".env") : "";
if (trim((string) getenv("APP_KEY")) === "" && ! preg_match("/^APP_KEY=base64:\S+/m", $env)) {
    fwrite(STDERR, "[entrypoint] FATAL: APP_KEY kosong.\n");
    exit(1);
}
'

echo "[entrypoint] Menjalankan migrasi ..."
php artisan migrate --force --no-interaction

echo "[entrypoint] Memuat ulang cache konfigurasi ..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Storage link hanya relevan jika dokumen disajikan publik (tidak dipakai di sini,
# karena file selalu di-stream lewat controller dengan cek hak akses).
chown -R www-data:www-data storage bootstrap/cache || true

echo "[entrypoint] Siap. Menjalankan php-fpm."
exec "$@"
