# Sistem Pemberkasan

Sistem manajemen dokumen berbasis web mirip Google Drive dengan kontrol akses user/admin.
Dibangun dengan **Laravel 11 + Livewire 3 + JWT**.

---

## Fitur Utama

| Fitur | Admin | User |
|---|---|---|
| CRUD folder (unlimited depth) | ✅ | ❌ |
| Upload/hapus file PDF | ✅ | ❌ |
| Rename folder & file | ✅ | ❌ |
| Tambah/hapus user | ✅ | ❌ |
| Auto-create root folder saat tambah user | ✅ | — |
| Cascade delete folder+file saat hapus user | ✅ | — |
| Assign akses folder ke user | ✅ | — |
| Browse folder yang diakses | — | ✅ |
| Preview PDF inline | ✅ | ✅ |
| Download PDF | ✅ | ✅ |
| Ganti password | — | ✅ |
| Autentikasi JWT (cookie HttpOnly) | ✅ | ✅ |

---

## Cara Setup

### 1. Install dependensi

```bash
composer install
cp .env.example .env
php artisan key:generate
```

### 2. Konfigurasi database

Edit `.env`:
```env
DB_DATABASE=sistem_pemberkasan
DB_USERNAME=root
DB_PASSWORD=yourpassword
```

### 3. Generate JWT secret

```bash
php artisan jwt:secret
```

### 4. Migrate & seed

```bash
php artisan migrate:fresh --seed
```

Akun default setelah seed:

| Role | Email | Password |
|---|---|---|
| Admin | admin@agrotek.com | Agr0t3knologi123 |

### 5. Link storage

```bash
php artisan storage:link
```

### 6. Jalankan server

```bash
php artisan serve
```

Buka: http://localhost:8000

---

## Struktur Project

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── AuthController.php       ← Login, logout, JWT refresh
│   │   └── FileController.php       ← Stream/download PDF
│   └── Middleware/
│       ├── JwtAuthenticate.php      ← Auth via cookie JWT
│       └── CheckRole.php            ← Guard admin/user
├── Livewire/
│   ├── Admin/
│   │   ├── UserManager.php          ← CRUD user
│   │   └── FolderManager.php        ← CRUD folder + file
│   └── User/
│       ├── FolderBrowser.php        ← Browse folder yang diakses
│       └── ChangePassword.php       ← Ganti password
├── Models/
│   ├── User.php                     ← Model user + JWT interface
│   ├── Folder.php                   ← Recursive self-referencing
│   └── File.php                     ← File PDF
├── Observers/
│   └── UserObserver.php             ← Auto folder + cascade delete
└── Services/
    ├── FolderService.php            ← Business logic folder
    └── FileService.php              ← Upload, rename, delete, stream

database/
└── migrations/
    ├── ..._create_users_table.php
    ├── ..._create_folders_table.php
    └── ..._create_files_and_pivot_table.php

resources/views/
├── layouts/app.blade.php            ← Layout utama + sidebar
├── auth/login.blade.php
└── livewire/
    ├── admin/
    │   ├── folder-manager.blade.php
    │   └── user-manager.blade.php
    └── user/
        ├── folder-browser.blade.php
        └── change-password.blade.php
```

---

## Arsitektur Database

```
users
  id (uuid, PK)
  name, email, password
  role: 'admin' | 'user'
  is_active, force_change_password
  deleted_at (soft delete)

folders
  id (uuid, PK)
  name
  parent_id → folders.id (nullable, self-join, cascade delete)
  user_id   → users.id   (nullable, root folder owner)
  is_root   (boolean)
  deleted_at (soft delete)

files
  id (uuid, PK)
  folder_id  → folders.id (cascade delete)
  name       (nama tampilan, bisa di-rename)
  original_name
  path       (path storage, tidak expose ke user)
  size, mime_type
  uploaded_by → users.id
  deleted_at (soft delete)

folder_user  (pivot: akses user ke folder)
  folder_id → folders.id (cascade)
  user_id   → users.id   (cascade)
```

---

## Alur Autentikasi JWT

1. User POST ke `/login` → `AuthController::login()`
2. JWT di-issue via `JWTAuth::attempt($credentials)`
3. Token disimpan di **cookie HttpOnly** (`jwt_token`) — tidak bisa diakses JS
4. Setiap request, `JwtAuthenticate` middleware membaca cookie, validasi token
5. Auto-refresh: token expired → middleware coba `JWTAuth::refresh()`, set cookie baru
6. Logout: `JWTAuth::invalidate()` + hapus cookie
7. Frontend auto-refresh token setiap 20 menit via fetch ke `/auth/refresh`

---

## Cara Deploy ke Production

```bash
# Di server
git clone [repo] && cd sistem-pemberkasan
composer install --no-dev --optimize-autoloader
cp .env.example .env
# Edit .env: APP_ENV=production, APP_DEBUG=false, DB_*, JWT_SECRET
php artisan key:generate
php artisan jwt:secret
php artisan migrate --force
php artisan optimize
php artisan storage:link
```

Pastikan:
- Nginx/Apache: root ke `/public`
- PHP 8.2+
- `storage/` dan `bootstrap/cache/` writable
- Aktifkan HTTPS (cookie HttpOnly + Secure flag)

---

## Catatan Pengembangan Lanjutan

- **Tailwind CDN** digunakan untuk kemudahan. Di production, ganti dengan Vite:
  ```bash
  npm install && npm run build
  ```
- **File storage**: default `local` disk. Untuk produksi besar, ganti ke S3:
  ```env
  FILESYSTEM_DISK=s3
  AWS_*=...
  ```
- **PDF Preview mobile**: iframe PDF tidak didukung semua browser mobile.
  Pertimbangkan integrasi [PDF.js](https://mozilla.github.io/pdf.js/) untuk preview yang lebih baik.
- **Middleware `force_change_password`**: tambahkan middleware yang redirect user
  ke halaman ganti password jika `force_change_password = true`.
