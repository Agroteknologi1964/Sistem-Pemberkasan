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
    protected $guarded = [];   

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'force_change_password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'force_change_password' => 'boolean',
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

    /**
     * Cek apakah user boleh mengakses folder tertentu.
     * Admin selalu boleh; user hanya jika ada di pivot.
     */
    public function canAccessFolder(Folder $folder): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        // Cek folder itu sendiri atau leluhur yang di-assign
        return $this->accessibleFolders()
            ->where('folders.id', $folder->id)
            ->exists();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
