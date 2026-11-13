# Sistem Pemberkasan

Sistem manajemen dokumen berbasis web untuk arsip surat dan berkas PDF, dengan kontrol akses
per folder antara admin dan user. Dibangun dengan **Laravel 11 + Livewire 3 + JWT**.

Dokumen **tidak pernah** disajikan langsung dari `public/`. Setiap preview dan download
melewati controller dengan pengecekan hak akses, dan tercatat di audit log.

---

## Daftar Isi

- [Fitur](#fitur)
- [Kebutuhan Sistem](#kebutuhan-sistem)
- [Setup Cepat dengan Docker](#setup-cepat-dengan-docker-disarankan)
- [Setup Manual](#setup-manual-tanpa-docker)
- [Akun Pertama](#akun-pertama)
- [Perintah Artisan](#perintah-artisan)
- [Struktur Project](#struktur-project)
- [Alur Autentikasi & 2FA](#alur-autentikasi--2fa)
- [Kontrol Akses Dokumen](#kontrol-akses-dokumen)
- [Arsitektur Database](#arsitektur-database)
- [Pengujian](#pengujian)
- [Deploy ke Production](#deploy-ke-production)
- [Pemecahan Masalah](#pemecahan-masalah)

---

## Fitur

### Dokumen & Folder

| Fitur | Admin | User |
|---|:---:|:---:|
| CRUD folder (kedalaman tak terbatas) | ✅ | ❌ |
| Upload / hapus file PDF | ✅ | ❌ |
| Rename folder & file | ✅ | ❌ |
| Browse folder yang diakses | ✅ | ✅ |
| Preview PDF inline | ✅ | ✅ |
| Download PDF | ✅ | ✅ |
| Auto-create root folder saat user dibuat | ✅ | — |
| Cascade delete folder + file saat user dihapus | ✅ | — |
| Assign akses folder ke user | ✅ | — |

Folder induk yang di-assign otomatis memberi akses ke **seluruh turunannya**
(subfolder, sub-subfolder, dst).

### Akun & Keamanan

| Fitur | Keterangan |
|---|---|
| Autentikasi JWT | Token di cookie **HttpOnly**, tidak bisa dibaca JavaScript |
| Verifikasi Dua Langkah (2FA) | TOTP 6 digit, kompatibel Google Authenticator / Authy / Microsoft Authenticator |
| Kode pemulihan | 10 kode sekali pakai, disimpan sebagai hash |
| Kebijakan password | Min. 10 karakter, wajib huruf besar + kecil + angka, tolak password umum |
| Pencabutan sesi | Ganti password → semua token lama langsung tidak berlaku |
| Rate limit login | 5 percobaan/menit per email+IP |
| Rate limit 2FA | 5 percobaan/menit per challenge |
| Security header | CSP, X-Frame-Options, nosniff, Referrer-Policy, HSTS |
| Audit akses dokumen | Setiap preview & download tercatat: siapa, kapan, IP, file mana |
| Manajemen user | CRUD user, aktif/nonaktif, reset password |
| Riwayat aktivitas | Semua perubahan model tercatat via spatie/laravel-activitylog |

### Antarmuka

| Fitur | Keterangan |
|---|---|
| Dark mode | Toggle di navbar, mengikuti preferensi OS, tersimpan di browser |
| Responsif | Layout adaptif untuk desktop dan tablet |

---

## Kebutuhan Sistem

### Untuk menjalankan tanpa Docker

| Komponen | Versi minimum |
|---|---|
| PHP | 8.2 (ekstensi `pdo_mysql`, `mbstring`, `bcmath`, `gd`, `zip`, `intl`) |
| Composer | 2.x |
| MySQL / MariaDB | MySQL 8.0 atau MariaDB 10.4 |
| Node.js | 18+ (hanya untuk build aset, opsional) |

### Untuk menjalankan dengan Docker

| Komponen | Versi |
|---|---|
| Docker Engine | 24+ |
| Docker Compose | v2 (`docker compose`, bukan `docker-compose`) |
| RAM | 2 GB minimum, disarankan swap 2 GB |

---

## Setup Cepat dengan Docker (disarankan)

Jalur ini mengemas PHP-FPM, Nginx, MySQL, dan Redis dalam satu stack.
Panduan lengkap termasuk TLS dan backup: **[DEPLOY.md](DEPLOY.md)**.

```bash
git clone https://github.com/Agroteknologi1964/Sistem-Pemberkasan.git
cd Sistem-Pemberkasan

# 1. Siapkan environment
cp .env.example .env
nano .env
```

Isi nilai berikut di `.env` — **wajib**, kalau kosong container menolak start:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-anda.ac.id
DB_DATABASE=sistem_pemberkasan
DB_USERNAME=pemberkasan
DB_PASSWORD=<password-kuat>
DB_ROOT_PASSWORD=<password-kuat-berbeda>
SEED_ADMIN_PASSWORD=<password-admin-awal>
```

```bash
# 2. Generate APP_KEY
docker run --rm -v "$PWD":/app -w /app php:8.2-cli-alpine \
  php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'
# → salin hasilnya ke APP_KEY=

# 3. Generate JWT_SECRET
openssl rand -hex 32
# → salin hasilnya ke JWT_SECRET=

# 4. Validasi konfigurasi sebelum build
./deploy/validate.sh

# 5. Deploy
./deploy/deploy.sh
```

`deploy.sh` otomatis: backup database, build image, jalankan migrasi,
cache konfigurasi, menunggu healthcheck, dan memverifikasi HTTP 200.

| Perintah | Fungsi |
|---|---|
| `./deploy/validate.sh` | 42 pemeriksaan: konsistensi batas upload, anggaran RAM, keamanan |
| `./deploy/deploy.sh` | Deploy penuh (build + migrate + verifikasi) |
| `./deploy/deploy.sh --quick` | Update kode tanpa rebuild image |
| `./deploy/deploy.sh --rollback` | Kembali ke image sebelumnya |
| `./deploy/backup.sh` | Backup database + dokumen + `.env` (retensi 14) |
| `./deploy/backup.sh --restore <file>` | Pulihkan database dari backup |

---

## Setup Manual (tanpa Docker)

```bash
git clone https://github.com/Agroteknologi1964/Sistem-Pemberkasan.git
cd Sistem-Pemberkasan

# 1. Dependensi PHP
composer install

# 2. Environment
cp .env.example .env
php artisan key:generate
```

Edit `.env` sesuai database lokal:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sistem_pemberkasan
DB_USERNAME=root
DB_PASSWORD=
```

```bash
# 3. Buat database (jalankan di MySQL/MariaDB)
#    CREATE DATABASE sistem_pemberkasan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# 4. JWT secret — WAJIB
#    Tanpa ini login gagal dengan error "Secret is not set"
php artisan jwt:secret

# 5. Migrasi + data awal
php artisan migrate --seed

# 6. Build aset (opsional, layout memakai Tailwind CDN)
npm install && npm run build

# 7. Jalankan
php artisan serve
```

Buka <http://localhost:8000> → otomatis diarahkan ke `/login`.

> **Catatan storage:** dokumen disimpan di `storage/app/documents/` dan **tidak**
> dapat diakses lewat URL. `php artisan storage:link` **tidak diperlukan** dan tidak
> dipakai oleh aplikasi.

---

## Akun Pertama

Seeder mengambil kredensial admin dari `.env`:

```env
SEED_ADMIN_EMAIL=admin@agrotek.com
SEED_ADMIN_PASSWORD=<password-yang-anda-tentukan>
```

- Jika `SEED_ADMIN_PASSWORD` kosong di `APP_ENV=production`, seeder **melewati** pembuatan
  admin dan menampilkan peringatan. Isi dulu, lalu jalankan `php artisan db:seed`.
- Seeder bersifat **idempoten** — aman dijalankan berulang, tidak error *duplicate entry*.
- Setelah login pertama, sistem **mewajibkan ganti password** (kebijakan min. 10 karakter).
- Disarankan langsung mengaktifkan 2FA di menu **Keamanan** setelah login.

Buat admin tambahan kapan saja tanpa menyentuh `.env`:

```bash
php artisan admin:create --email=admin2@domain.ac.id
# Password ditanyakan lewat prompt tersembunyi, tidak tercatat di shell history
```

---

## Perintah Artisan

| Perintah | Fungsi |
|---|---|
| `php artisan admin:create` | Buat akun admin baru (password via prompt tersembunyi) |
| `php artisan user:reset-password {email}` | Reset password user jadi acak, tandai wajib ganti |
| `php artisan jwt:secret --force` | Generate / ganti `JWT_SECRET` |
| `php artisan migrate:fresh --seed` | Reset database dari nol |

Opsi `admin:create`:

```bash
php artisan admin:create \
  --name="Nama Admin" \
  --email=admin@domain.ac.id
# --password= bisa dipakai untuk non-interaktif (CI/CD), tapi password
# akan terlihat di riwayat shell — hindari di mesin bersama.
```

### Scheduler

`routes/console.php` menjadwalkan:

- `jwt:purge-blacklist` — setiap hari
- Pembersihan permanen record soft-delete > 30 hari — setiap Minggu 02:00

Agar berjalan, harus ada proses penjadwal. Di Docker, tambahkan service:

```yaml
  scheduler:
    image: pemberkasan-app:latest
    restart: unless-stopped
    env_file: .env
    environment:
      DB_HOST: db
      REDIS_HOST: redis
    depends_on:
      db: { condition: service_healthy }
      redis: { condition: service_healthy }
    networks: [backend]
    command: php artisan schedule:work
```

Di VPS tanpa Docker, gunakan cron:

```cron
* * * * * cd /var/www/pemberkasan && php artisan schedule:run >> /dev/null 2>&1
```

---

## Struktur Project

Lapisan yang dipakai: **MVC yang diperluas** — komponen Livewire mengambil peran
Controller untuk halaman CRUD, sedangkan logika bisnis dipisah ke Service.

```
app/
├── Console/Commands/
│   ├── CreateAdminCommand.php              ← admin:create
│   └── ResetUserPasswordCommand.php        ← user:reset-password
├── Http/
│   ├── Controllers/
│   │   ├── AuthController.php              ← Login, logout, challenge 2FA, refresh
│   │   └── FileController.php              ← Preview/download + audit log
│   ├── Middleware/
│   │   ├── JwtAuthenticate.php             ← Auth via cookie JWT
│   │   ├── CheckRole.php                   ← Guard admin/user
│   │   ├── ForceChangePassword.php         ← Paksa ganti password
│   │   ├── RejectTokensIssuedBeforePasswordChange.php
│   │   ├── RedirectIfAuthenticated.php     ← guest.jwt
│   │   └── SecurityHeaders.php             ← CSP, X-Frame-Options, dll
│   └── Requests/Auth/LoginRequest.php
├── Livewire/
│   ├── Admin/
│   │   ├── Dashboard.php
│   │   ├── FolderManager.php               ← CRUD folder + upload file
│   │   ├── UserManager.php                 ← CRUD user
│   │   └── ActivityLog.php
│   └── User/
│       ├── FolderBrowser.php               ← Browse folder yang diakses
│       ├── ChangePassword.php
│       └── Security.php                    ← Pengaturan 2FA
├── Models/
│   ├── User.php                            ← JWT + access control + 2FA
│   ├── Folder.php                          ← Self-referencing hierarki
│   └── File.php
├── Observers/UserObserver.php              ← Auto root folder + cascade delete
├── Policies/                               ← FilePolicy, FolderPolicy
├── Providers/
│   ├── AppServiceProvider.php
│   ├── AuthServiceProvider.php
│   └── SecurityServiceProvider.php         ← Rate limiter (login, 2FA, upload, api)
├── Rules/NotCommonPassword.php             ← Validasi password lokal (tanpa jaringan)
└── Services/
    ├── FolderService.php
    ├── FileService.php                     ← Upload, rename, delete, stream
    ├── DocumentAccessLogger.php            ← Audit akses dokumen
    ├── TotpService.php                     ← Implementasi TOTP RFC 6238
    └── TwoFactorChallenge.php              ← Cookie challenge 2FA

deploy/
├── php.ini                                 ← Produksi + batas upload 20 MB
├── php-fpm-pool.conf                       ← 3 worker (RAM 2 GB)
├── nginx.conf                              ← TLS, deny .env/.git
├── entrypoint.sh                           ← Tunggu DB → migrate → cache → FPM
├── validate.sh                             ← 42 pemeriksaan pra-deploy
├── deploy.sh                               ← Build, backup, healthcheck, rollback
└── backup.sh                               ← Backup DB + dokumen + .env

database/migrations/                        ← 9 migrasi
resources/views/
├── components/layouts/app.blade.php        ← Layout utama + sidebar + toggle tema
├── auth/login.blade.php
├── auth/two-factor.blade.php
└── livewire/{admin,user}/
public/js/theme.js                          ← Dark mode (dimuat sebelum paint)
tests/
├── Feature/{DocumentAccess,TwoFactorLogin,PasswordPolicy,Example}Test.php
└── Unit/{Example,TotpService}Test.php
```

---

## Alur Autentikasi & 2FA

### Login biasa (tanpa 2FA)

```
POST /login
  → Rate limit 5/menit per email+IP
  → LoginRequest validasi
  → JWTAuth::attempt() → token
  → Cookie HttpOnly jwt_token (Secure di production)
  → Redirect sesuai role
```

### Login dengan 2FA aktif

```
POST /login
  → Password benar
  → Token JWT yang baru dibuat LANGSUNG di-invalidate
  → Cookie 2fa_challenge terenkripsi (berlaku 5 menit)
  → Redirect ke /two-factor

POST /two-factor
  → Rate limit 5/menit per challenge
  → Verifikasi TOTP atau kode pemulihan
  → Kode pemulihan yang dipakai langsung dihapus
  → JWT baru diterbitkan → redirect sesuai role
```

JWT **tidak diterbitkan** sebelum kode 2FA terbukti valid. Ini yang membuat
pencurian password saja tidak cukup untuk masuk.

### Siklus token

| Tahap | Middleware / komponen | Perilaku |
|---|---|---|
| Setiap request | `JwtAuthenticate` | Baca cookie, validasi, isi `auth()->user()` |
| Token kedaluwarsa | `JwtAuthenticate` | Auto-refresh, set cookie baru |
| Password baru diganti | `RejectTokensIssuedBeforePasswordChange` | Token dengan `iat` lebih tua ditolak |
| Setiap 20 menit | Layout | Fetch `POST /auth/refresh` sebagai heartbeat |
| Logout | `AuthController` | Blacklist token + hapus cookie |

---

## Kontrol Akses Dokumen

Aturan tunggal, dipakai di browse, preview, dan download:

```php
$user->canAccessFolder($folder)
```

- **Admin** → selalu boleh
- **User** → boleh jika folder itu sendiri **atau salah satu leluhurnya** ada di pivot `folder_user`
- Akses mengalir **ke bawah** (assign induk = bisa semua turunannya), **tidak ke atas**
- Penelusuran dibatasi 50 level untuk mencegah loop bila `parent_id` terkorupsi

Setiap akses berhasil dicatat ke tabel `activity_log` dengan `log_name = 'document-access'`:

```
event    : preview | download
deskripsi: Pengguna mengunduh dokumen "Surat Undangan"
file     : Surat Undangan | folder: Berkas Dosen
ip       : 103.x.x.x
causer   : App\Models\User / <uuid>
```

Akses yang **ditolak** (403) tidak dicatat sebagai akses berhasil.

---

## Arsitektur Database

```
users
  id (uuid, PK)
  name, email (unique), password
  role: 'admin' | 'user'
  is_active, force_change_password
  two_factor_secret            (terenkripsi)
  two_factor_confirmed_at
  two_factor_recovery_codes    (terenkripsi, hash bcrypt)
  password_changed_at          → dasar pencabutan token lama
  deleted_at (soft delete)

folders
  id (uuid, PK)
  name
  parent_id → folders.id (nullable, self-join, cascade delete)
  user_id   → users.id   (nullable, root folder owner)
  is_root (boolean), order
  deleted_at (soft delete)

files
  id (uuid, PK)
  folder_id  → folders.id (cascade delete)
  name       (nama tampilan, bisa di-rename)
  original_name
  path       (path storage, tidak di-expose ke user)
  mime_type, size (bytes)
  uploaded_by → users.id
  deleted_at (soft delete)

folder_user  (pivot: akses user ke folder)
  folder_id → folders.id (cascade)
  user_id   → users.id   (cascade)
  PK gabungan (folder_id, user_id)

activity_log  (spatie/laravel-activitylog)
  log_name, description, event
  subject_type / subject_id  (polymorphic)
  causer_type / causer_id
  properties (json: nilai lama & baru, IP, user agent)
```

---

## Pengujian

```bash
php artisan test
```

Cakupan saat ini: **44 test, 136 assertion**.

| File | Cakupan |
|---|---|
| `Unit/TotpServiceTest` | TOTP divalidasi terhadap 6 test vector resmi RFC 6238 |
| `Feature/DocumentAccessTest` | Warisan akses folder, audit log, penolakan 403 |
| `Feature/TwoFactorLoginTest` | JWT tidak terbit sebelum 2FA valid, kode pemulihan sekali pakai, secret terenkripsi |
| `Feature/PasswordPolicyTest` | Kebijakan password, pencabutan token setelah ganti password |
| `Feature/ExampleTest` | Redirect root, security header, rate limit login |

Menjalankan sebagian:

```bash
php artisan test --filter=DocumentAccessTest
php artisan test --filter=TotpServiceTest
```

---

## Deploy ke Production

Panduan lengkap: **[DEPLOY.md](DEPLOY.md)** — mencakup sertifikat TLS, backup terjadwal,
lokasi penyimpanan dokumen, dan checklist go-live.

### Ringkas — Docker (disarankan)

```bash
cd /var/www/pemberkasan
./deploy/validate.sh     # harus LULUS
./deploy/deploy.sh
```

### Ringkas — manual

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env     # APP_ENV=production, APP_DEBUG=false, HTTPS
php artisan key:generate
php artisan jwt:secret --force
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Checklist minimum sebelum go-live:

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `APP_KEY` dan `JWT_SECRET` terisi
- [ ] `APP_URL` memakai `https://`
- [ ] `SESSION_SECURE_COOKIE=true`
- [ ] Nginx/Apache root ke folder `public/`
- [ ] `storage/` dan `bootstrap/cache/` writable
- [ ] Password admin awal sudah diganti
- [ ] 2FA admin sudah diaktifkan
- [ ] Backup database + volume dokumen terjadwal
- [ ] Proses `schedule:run` / `schedule:work` berjalan

### Penyimpanan dokumen

| Lingkungan | Lokasi default |
|---|---|
| Lokal | `storage/app/documents/` |
| Docker | Volume `documents` |
| S3 (opsional) | Set `DOCUMENTS_DISK=s3` + kredensial AWS |

Jangan pernah membuat symlink dokumen ke `public/`. File harus selalu lewat
`FileController` agar pengecekan hak akses dan audit log tidak terlewati.

---

## Pemecahan Masalah

| Gejala | Penyebab & solusi |
|---|---|
| `Secret is not set` saat login / seed | `JWT_SECRET` kosong → `php artisan jwt:secret --force` |
| Halaman putih / error 500 | `composer install` belum dijalankan |
| `Unknown database 'sistem_pemberkasan'` | Database belum dibuat manual |
| `Duplicate entry 'admin@agrotek.com'` | Seeder versi lama — versi sekarang idempoten, aman dijalankan ulang |
| Login berhasil tapi diarahkan ke ganti password | Normal: `force_change_password=true` untuk admin baru |
| Password baru ditolak terus | Kebijakan: min. 10 karakter, huruf besar+kecil, angka, bukan password umum/nama/email |
| Layout berantakan / tanpa warna | Tailwind CDN diblokir → `npm install && npm run build` lalu aktifkan `@vite` |
| Upload > 20 MB ditolak | Batas berlapis: `MAX_FILE_SIZE` (env) ≤ `upload_max_filesize` (php.ini) ≤ `client_max_body_size` (nginx) |
| Dokumen 404 setelah deploy | Volume `documents` belum ter-mount, atau migrasi data belum dijalankan |
| Semua request di-redirect ke `/login` | Cookie `jwt_token` tidak terkirim — cek `APP_URL`, HTTPS, dan `SESSION_SECURE_COOKIE` |

---

## Catatan Pengembangan Lanjutan

- **Tailwind CDN**: layout saat ini memuat `cdn.tailwindcss.com`. Untuk produksi,
  aktifkan Vite (`resources/css/app.css` + `resources/js/app.js` sudah siap):
  `npm install && npm run build`, lalu ganti tag CDN dengan `@vite([...])`.
  Build Vite sudah terverifikasi menghasilkan kelas `dark:` yang benar.
- **Policy belum terpakai**: `FilePolicy` dan `FolderPolicy` terdaftar tetapi belum
  dipanggil di kode. Pengecekan akses saat ini langsung lewat `User::canAccessFolder()`.
  Jika ingin memusatkan aturan, pindahkan logika ke Policy dan panggil `$this->authorize()`.
- **PDF preview mobile**: `<iframe>` PDF tidak didukung semua browser mobile.
  Pertimbangkan [PDF.js](https://mozilla.github.io/pdf.js/) untuk preview lintas perangkat.
- **Enkripsi dokumen at-rest**: PDF saat ini tersimpan sebagai byte asli di disk.
  Untuk data yang sangat sensitif, pertimbangkan enkripsi per-file atau LUKS pada volume.
- **PostgreSQL**: migrasi memungkinkan, tetapi `LIKE` di PostgreSQL bersifat
  case-sensitive sehingga 5 query pencarian perlu diubah ke `ilike`/`LOWER()`.

---

## Lisensi

MIT
