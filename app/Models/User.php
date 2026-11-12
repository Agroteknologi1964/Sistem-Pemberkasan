<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, HasUuids, Notifiable, LogsActivity;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'force_change_password',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'force_change_password' => 'boolean',
        // Secret TOTP & kode pemulihan tidak boleh tersimpan plaintext:
        // kalau database bocor, secret itu membangkitkan kode 2FA yang sah.
        'two_factor_secret' => 'encrypted',
        'two_factor_recovery_codes' => 'encrypted:array',
        'two_factor_confirmed_at' => 'datetime',
        'password_changed_at' => 'datetime',
    ];

    /**
     * Secret 2FA tidak pernah ikut terserialisasi ke JSON/response.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    // ─── JWT Interface ───────────────────────────────────────────────────────

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'role' => $this->role,
            'name' => $this->name,
        ];
    }

    // ─── Relasi ─────────────────────────────────────────────────────────────

    /**
     * Folder root yang otomatis dibuat saat user dibuat.
     */
    public function rootFolder(): HasOne
    {
        return $this->hasOne(Folder::class, 'user_id')->where('is_root', true);
    }

    /**
     * Semua folder milik user (root dan turunannya).
     */
    public function ownedFolders(): HasMany
    {
        return $this->hasMany(Folder::class, 'user_id');
    }

    /**
     * Folder yang diberi akses oleh admin (pivot).
     */
    public function accessibleFolders(): BelongsToMany
    {
        return $this->belongsToMany(Folder::class, 'folder_user');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    // ─── Two-Factor Authentication ───────────────────────────────────────────

    /**
     * 2FA aktif hanya kalau secret sudah dibuat DAN sudah dikonfirmasi
     * dengan kode yang valid. Secret yang belum dikonfirmasi tidak boleh
     * memblokir login — pengguna masih dalam proses setup.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return ! empty($this->two_factor_secret)
            && $this->two_factor_confirmed_at !== null;
    }

    /**
     * Admin tingkat tinggi: kebijakan bisa diwajibkan untuk mereka lebih dulu.
     * Dipakai untuk peringatan di dashboard, bukan untuk memblokir.
     */
    public function requiresTwoFactor(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Buat 10 kode pemulihan sekali pakai.
     *
     * Disimpan sebagai hash bcrypt, bukan plaintext: kode asli hanya
     * ditampilkan sekali ke pengguna saat setup.
     */
    public function generateRecoveryCodes(): array
    {
        $plain = [];
        $hashed = [];

        for ($i = 0; $i < 10; $i++) {
            $code     = strtoupper(\Illuminate\Support\Str::random(10));
            $plain[]  = $code;
            $hashed[] = \Illuminate\Support\Facades\Hash::make($code);
        }

        $this->forceFill(['two_factor_recovery_codes' => $hashed])->save();

        return $plain;
    }

    /**
     * Pakai satu kode pemulihan. Kode yang cocok langsung dihapus sehingga
     * tidak bisa dipakai dua kali, bahkan kalau request diulang.
     */
    public function consumeRecoveryCode(string $code): bool
    {
        $hashes = $this->two_factor_recovery_codes ?? [];
        $code   = strtoupper(trim($code));

        foreach ($hashes as $index => $hash) {
            if (\Illuminate\Support\Facades\Hash::check($code, $hash)) {
                unset($hashes[$index]);
                $this->forceFill(['two_factor_recovery_codes' => array_values($hashes)])->save();

                return true;
            }
        }

        return false;
    }

    /**
     * Admin selalu boleh; user hanya jika folder ini ATAU salah satu leluhurnya
     * ada di pivot folder_user.
     *
     * Assign akses ke sebuah folder otomatis memberi akses ke seluruh turunannya
     * (subfolder, sub-subfolder, dst). Sebelumnya method ini hanya mencocokkan
     * id folder secara persis, sehingga user yang di-assign ke folder induk
     * ditolak 403 saat membuka file di subfolder.
     */
    public function canAccessFolder(Folder $folder): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        // Kumpulkan ID folder yang di-assign SEKALI (hindari query per leluhur).
        $assignedIds = $this->accessibleFolders()->pluck('folders.id')->all();

        if (empty($assignedIds)) {
            return false;
        }

        // Telusuri diri sendiri lalu ke atas. Batas kedalaman mencegah loop
        // tak berujung bila data parent_id terkorupsi (siklus).
        $current  = $folder;
        $depth    = 0;
        $maxDepth = 50;

        while ($current !== null && $depth < $maxDepth) {
            if (in_array($current->id, $assignedIds, true)) {
                return true;
            }

            $current = $current->parent;
            $depth++;
        }

        return false;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
