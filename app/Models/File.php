<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class File extends Model
{
    use HasFactory, HasUuids, SoftDeletes, LogsActivity;
    
    protected $guarded = [];   

    protected $fillable = [
        'folder_id',
        'name',
        'original_name',
        'path',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    // ─── Relasi ─────────────────────────────────────────────────────────────

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * URL preview PDF sesuai role user.
     */
    public function previewUrl(): string
    {
        return auth()->user()?->isAdmin()
            ? route('admin.files.preview', $this->id)
            : route('user.files.preview', $this->id);
    }

    /**
     * URL download PDF sesuai role user.
     */
    public function downloadUrl(): string
    {
        return auth()->user()?->isAdmin()
            ? route('admin.files.download', $this->id)
            : route('user.files.download', $this->id);
    }

    /**
     * Ukuran file dalam format human-readable.
     */
    public function humanSize(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $bytes >= 1024 && $i < 3; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 1) . ' ' . $units[$i];
    }

    /**
     * Hapus file fisik dari storage disk yang aktif.
     */
    public function deletePhysicalFile(): bool
    {
        $disk = config('filesystems.documents_disk', config('filesystems.default', 'local'));
        if (Storage::disk($disk)->exists($this->path)) {
            return Storage::disk($disk)->delete($this->path);
        }
        return true;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
