<?php

namespace App\Services;

use App\Models\File;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mencatat akses dokumen ke tabel activity_log.
 *
 * Model File sudah memakai LogsActivity, tetapi trait itu hanya menangkap
 * create/update/delete pada model — bukan membuka atau mengunduh isi PDF.
 * Untuk dokumen surat yang bersifat kredensial, setiap pembukaan file harus
 * meninggalkan jejak siapa, kapan, dan dari IP mana.
 *
 * Pencatatan sengaja dibuat best-effort: kegagalan menulis log TIDAK boleh
 * menggagalkan pengiriman file (audit trail rusak tidak boleh memblokir kerja),
 * tetapi kegagalannya tetap ditulis ke laravel.log agar bisa diperbaiki.
 */
class DocumentAccessLogger
{
    public const LOG_NAME = 'document-access';

    public const EVENT_PREVIEW  = 'preview';
    public const EVENT_DOWNLOAD = 'download';

    /**
     * Catat akses dokumen.
     *
     * @param  string  $event  self::EVENT_PREVIEW | self::EVENT_DOWNLOAD
     */
    public function record(File $file, ?User $user, string $event): void
    {
        try {
            $properties = [
                'event'       => $event,
                'file_id'     => $file->id,
                'file_name'   => $file->name,
                'folder_id'   => $file->folder_id,
                'folder_name' => $file->folder?->name,
                'file_path'   => $file->path,
                'size'        => $file->size,
                'ip'          => request()->ip(),
                'user_agent'  => Str::limit((string) request()->userAgent(), 255, ''),
            ];

            // Tulis langsung ke tabel (bukan lewat trait model) supaya event
            // custom ini tidak tercampur dengan log perubahan atribut.
            DB::table(config('activitylog.table_name', 'activity_log'))->insert([
                'log_name'     => self::LOG_NAME,
                'description'  => $this->description($file, $event),
                'event'        => $event,
                'subject_type' => $file->getMorphClass(),
                'subject_id'   => $file->getKey(),
                'causer_type'  => $user?->getMorphClass(),
                'causer_id'    => $user?->getKey(),
                'properties'   => json_encode($properties, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'batch_uuid'   => (string) Str::uuid(),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            // Jangan pernah menggagalkan akses file hanya karena log gagal.
            report($e);
        }
    }

    private function description(File $file, string $event): string
    {
        $action = $event === self::EVENT_DOWNLOAD ? 'mengunduh' : 'membuka pratinjau';

        return "Pengguna {$action} dokumen \"{$file->name}\"";
    }
}
