<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Folder extends Model
{
    use HasFactory, HasUuids, SoftDeletes, LogsActivity;

    protected $guarded = [];    

    protected $fillable = [
        'name',
        'parent_id',
        'user_id',
        'is_root',
        'order',
    ];

    protected $casts = [
        'is_root' => 'boolean',
    ];

    // ─── Relasi ─────────────────────────────────────────────────────────────

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    /**
     * Subfolder langsung (satu level).
     */
    public function children(): HasMany
    {
        return $this->hasMany(Folder::class, 'parent_id')->orderBy('order')->orderBy('name');
    }

    /**
     * Subfolder beserta turunannya (rekursif eager load).
     */
    public function allChildren(): HasMany
    {
        return $this->hasMany(Folder::class, 'parent_id')
            ->with('allChildren')
            ->orderBy('order')
            ->orderBy('name');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class)->orderBy('name');
    }

    public function accessUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'folder_user');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Breadcrumb dari root ke folder ini.
     */
    public function breadcrumbs(): array
    {
        $crumbs = collect();
        $folder = $this;

        while ($folder) {
            $crumbs->prepend(['id' => $folder->id, 'name' => $folder->name]);
            $folder = $folder->parent;
        }

        return $crumbs->all();
    }

    /**
     * Apakah folder ini kosong (tidak ada subfolder dan file).
     */
    public function isEmpty(): bool
    {
        return $this->children()->count() === 0 && $this->files()->count() === 0;
    }

    /**
     * Hapus semua file fisik secara rekursif sebelum model dihapus.
     * Dipanggil dari FolderObserver.
     */
    public function deleteFilesRecursively(): void
    {
        $this->files->each(fn ($file) => $file->deletePhysicalFile());

        $this->children->each(function ($child) {
            $child->deleteFilesRecursively();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
