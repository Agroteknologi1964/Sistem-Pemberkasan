# Panduan Deploy — Sistem Pemberkasan

Target: **VPS Jagoan Hosting, 2 GB RAM / 200 GB disk**, Ubuntu 22.04 LTS.
Stack: Docker Compose (PHP-FPM 8.2 + Nginx + MySQL 8 + Redis).

---

## 0. Pilihan: Docker atau tanpa Docker

| | Docker (direkomendasikan) | Tanpa Docker (manual) |
|---|---|---|
| Setup awal | ~20 menit | ~1–2 jam |
| Konsistensi versi PHP | Terjamin | Harus cocokkan manual |
| RAM terpakai | ±1,4 GB | ±1,1 GB |
| Update aplikasi | `git pull && docker compose up -d --build` | `git pull && composer install && migrate && cache` |
| Risiko | Rendah | Tinggi (salah versi ekstensi PHP) |

**Saran: pakai Docker.** 2 GB RAM cukup, tapi wajib tambah swap 2 GB (langkah 1).

---

## 1. Persiapan server

```bash
# Login ke VPS
ssh root@IP_VPS_ANDA

# Update sistem
apt update && apt upgrade -y

# Swap 2 GB — WAJIB untuk RAM 2 GB, mencegah OOM saat build image
fallocate -l 2G /swapfile
chmod 600 /swapfile
mkswap /swapfile
swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab
free -h   # harus terlihat Swap: 2.0Gi

# Install Docker
curl -fsSL https://get.docker.com | sh
docker --version
docker compose version
```

---

## 2. Ambil kode dan siapkan environment

```bash
mkdir -p /var/www && cd /var/www
git clone <URL_REPO_ANDA> pemberkasan
cd pemberkasan

cp .env.example .env
nano .env
```

Isi yang **wajib** diubah di `.env`:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-anda.ac.id

DB_DATABASE=sistem_pemberkasan
DB_USERNAME=pemberkasan
DB_PASSWORD=<password-kuat>
DB_ROOT_PASSWORD=<password-root-kuat-berbeda>

SEED_ADMIN_EMAIL=admin@agrotek.com
SEED_ADMIN_PASSWORD=<password-admin-awal>
```

Generate `APP_KEY` dan `JWT_SECRET`:

```bash
docker run --rm -v "$PWD":/app -w /app php:8.2-cli-alpine sh -c \
  "apk add --no-cache git unzip >/dev/null && php -r 'echo \"base64:\".base64_encode(random_bytes(32)).PHP_EOL;'"
# → salin hasilnya ke APP_KEY=

# JWT_SECRET: 64 karakter acak
openssl rand -hex 32
# → salin ke JWT_SECRET=
```

> **Penting:** `JWT_SECRET` kosong menyebabkan error `Secret is not set` saat login
> dan seeder. Entrypoint Docker akan menolak start jika nilainya kosong.

---

## 3. Siapkan sertifikat TLS

Nginx dikonfigurasi memaksa HTTPS. Terbitkan sertifikat **sebelum** `docker compose up`,
atau ganti sementara `deploy/nginx.conf` ke blok HTTP saja.

```bash
# Arahkan domain ke IP VPS dulu (A record), tunggu propagasi.
docker run --rm \
  -v "$PWD/certbot/conf:/etc/letsencrypt" \
  -v "$PWD/certbot/www:/var/www/certbot" \
  certbot/certbot certonly --standalone \
  -d domain-anda.ac.id --email admin@domain-anda.ac.id \
  --agree-tos --non-interactive
```

Lalu sesuaikan nama domain di `deploy/nginx.conf`:

```bash
sed -i 's/DOMAIN_ANDA/domain-anda.ac.id/g' deploy/nginx.conf
```

Perpanjangan otomatis (tambahkan ke `crontab -e`):

```
0 3 * * * cd /var/www/pemberkasan && docker run --rm -v "$PWD/certbot/conf:/etc/letsencrypt" -v "$PWD/certbot/www:/var/www/certbot" certbot/certbot renew --quiet && docker compose exec nginx nginx -s reload
```

---

## 4. Build dan jalankan

```bash
docker compose up -d --build
```

Saat pertama kali, entrypoint otomatis:
1. Menunggu MySQL siap
2. Memvalidasi `JWT_SECRET` dan `APP_KEY`
3. `php artisan migrate --force`
4. `config:cache`, `route:cache`, `view:cache`

Cek status:

```bash
docker compose ps
docker compose logs -f app
```

Seeder admin awal (hanya sekali):

```bash
docker compose exec app php artisan db:seed --force
```

Buka `https://domain-anda.ac.id` → harus redirect ke `/login`.

---

## 5. Lokasi data (disk 200 GB)

| Data | Lokasi | Backup |
|---|---|---|
| Dokumen PDF | Docker volume `documents` | `docker run --rm -v pemberkasan_documents:/d -v $PWD/backup:/b alpine tar czf /b/dokumen.tgz -C /d .` |
| Database | Docker volume `db-data` | `docker compose exec db mysqldump -u root -p"$DB_ROOT_PASSWORD" --single-transaction sistem_pemberkasan \| gzip > backup/db.sql.gz` |
| Log | Volume `app-logs` | `docker compose logs app > backup/app.log` |

Dokumen **tidak** disajikan langsung oleh Nginx — selalu lewat `FileController`
dengan cek `canAccessFolder()`. Jangan buat symlink `storage/app/documents` ke `public/`.

Backup harian (crontab):

```
0 2 * * * cd /var/www/pemberkasan && mkdir -p backup && docker compose exec -T db sh -c 'mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" --single-transaction "$MYSQL_DATABASE"' | gzip > backup/db-$(date +\%F).sql.gz
```

---

## 6. Update aplikasi

```bash
cd /var/www/pemberkasan
git pull
docker compose up -d --build   # entrypoint menjalankan migrate + cache otomatis
docker compose exec app php artisan queue:restart  # jika nanti memakai queue
```

---

## 7. Tanpa Docker (alternatif)

```bash
apt install -y nginx mysql-server php8.2-fpm php8.2-mysql php8.2-mbstring \
  php8.2-xml php8.2-bcmath php8.2-intl php8.2-zip php8.2-gd redis-server composer git

cd /var/www/pemberkasan
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan jwt:secret --force
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
chown -R www-data:storage bootstrap/cache
```

Salin `deploy/php.ini` ke `/etc/php/8.2/fpm/conf.d/99-app.ini`,
`deploy/php-fpm-pool.conf` ke `/etc/php/8.2/fpm/pool.d/zz-app.conf`,
daftarkan blok `server {}` dari `deploy/nginx.conf` ke
`/etc/nginx/sites-available/pemberkasan`, lalu:

```bash
ln -s /etc/nginx/sites-available/pemberkasan /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx php8.2-fpm
```

Alternatif tanpa Docker yang lebih aman: pakai **Laravel Forge** atau **Ploi**
(mengelola Nginx + PHP + deploy otomatis).

---

## 8. Checklist sebelum go-live

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `APP_KEY` dan `JWT_SECRET` terisi (bukan kosong)
- [ ] `APP_URL` memakai `https://`
- [ ] Password admin awal sudah diganti setelah login pertama
- [ ] `SESSION_SECURE_COOKIE=true`
- [ ] HTTPS aktif dan HTTP redirect ke HTTPS
- [ ] Backup database + volume dokumen terjadwal
- [ ] `docker compose logs app` bersih dari error
- [ ] Coba login, upload PDF, preview, download, logout
- [ ] Coba 6× login salah → harus muncul pesan "Terlalu banyak percobaan login"
