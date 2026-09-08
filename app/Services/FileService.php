<?php

namespace App\Services;

use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileService
{
    /**
     * Dapatkan nama storage disk dokumen yang aktif (local / s3 / etc).
     */
    public static function getDisk(): string
    {
        return config('filesystems.documents_disk', config('filesystems.default', 'local'));
    }

    /**
     * Upload file PDF ke folder tertentu.
     */
    public function upload(UploadedFile $uploadedFile, Folder $folder, User $uploader): File
    {
        // Validasi mime type
        if ($uploadedFile->getMimeType() !== 'application/pdf') {
            throw new \InvalidArgumentException('Hanya file PDF yang diperbolehkan.');
        }

        // Validasi ukuran (default max 20MB)
        $maxSize = config('app.max_file_size', 20480) * 1024; // convert KB to bytes
        if ($uploadedFile->getSize() > $maxSize) {
            throw new \InvalidArgumentException('Ukuran file melebihi batas maksimum.');
        }

        // Generate path storage yang aman (tidak expose nama asli)
        $storagePath = 'documents/' . $folder->id . '/' . Str::uuid() . '.pdf';

        // Simpan file ke storage disk yang aktif
        Storage::disk(self::getDisk())->put($storagePath, file_get_contents($uploadedFile->getRealPath()));

        // Simpan record ke database
        return File::create([
            'folder_id'     => $folder->id,
            'name'          => pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME),
            'original_name' => $uploadedFile->getClientOriginalName(),
            'path'          => $storagePath,
            'mime_type'     => 'application/pdf',
            'size'          => $uploadedFile->getSize(),
            'uploaded_by'   => $uploader->id,
        ]);
    }

    /**
     * Rename file (hanya admin).
     */
    public function rename(File $file, string $newName): File
    {
        // Bersihkan nama dari karakter berbahaya
        $cleanName = preg_replace('/[^a-zA-Z0-9\s\-_\.]/', '', $newName);
        $cleanName = trim($cleanName);

        if (empty($cleanName)) {
            throw new \InvalidArgumentException('Nama file tidak valid.');
        }

        $file->update(['name' => $cleanName]);
        return $file->fresh();
    }

    /**
     * Soft delete file (database record saja, file fisik tetap aman di storage untuk restore).
     */
    public function delete(File $file): bool
    {
        return (bool) $file->delete();
    }

    /**
     * Hapus permanen file (database record + file fisik).
     */
    public function forceDelete(File $file): bool
    {
        $file->deletePhysicalFile();
        return (bool) $file->forceDelete();
    }

    /**
     * Stream file PDF ke browser (untuk preview atau download).
     */
    public function stream(File $file, bool $download = false): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $disk = self::getDisk();

        if (! Storage::disk($disk)->exists($file->path)) {
            abort(404, 'File tidak ditemukan di storage.');
        }

        $disposition = $download ? 'attachment' : 'inline';
        $filename    = $file->name . '.pdf';

        return Storage::disk($disk)->response($file->path, $filename, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => $disposition . '; filename="' . $filename . '"',
        ]);
    }
}
