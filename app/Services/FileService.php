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
        if (! $uploadedFile->isValid()) {
            throw new \InvalidArgumentException('Upload gagal atau file rusak.');
        }

        // Validasi ukuran (default max 20MB) — dicek sebelum membaca isi file.
        $maxSize = (int) config('app.max_file_size', 20480) * 1024;
        if ($uploadedFile->getSize() > $maxSize) {
            throw new \InvalidArgumentException('Ukuran file melebihi batas maksimum.');
        }

        // Validasi isi file, bukan hanya header Content-Type kiriman client.
        // File PDF asli selalu diawali magic bytes "%PDF-" di 1024 byte pertama.
        $handle = fopen($uploadedFile->getRealPath(), 'rb');
        $head   = $handle ? fread($handle, 1024) : '';
        if ($handle) {
            fclose($handle);
        }

        if ($head === '' || ! str_contains($head, '%PDF-')) {
            throw new \InvalidArgumentException('File bukan PDF yang valid.');
        }

        // Path storage aman: UUID, tidak memakai nama asli dari client.
        $storagePath = 'documents/' . $folder->id . '/' . Str::uuid() . '.pdf';

        // Stream ke disk tanpa memuat seluruh file ke memory (penting di VPS RAM kecil).
        $stream = fopen($uploadedFile->getRealPath(), 'rb');
        Storage::disk(self::getDisk())->writeStream($storagePath, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        // Pastikan file benar-benar tersimpan sebelum mencatat ke database.
        if (! Storage::disk(self::getDisk())->exists($storagePath)) {
            throw new \RuntimeException('Gagal menyimpan file ke storage.');
        }

        return File::create([
            'folder_id'     => $folder->id,
            'name'          => Str::limit(
                preg_replace('/[^\p{L}\p{N}\s\-_\.]/u', '', pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME)),
                180,
                ''
            ) ?: 'dokumen',
            'original_name' => Str::limit($uploadedFile->getClientOriginalName(), 255, ''),
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

        // Sanitasi nama file di header agar tidak bisa menyuntikkan header baru
        // lewat nama file (mis. `"; evil`). RFC 6266: fallback ASCII + filename* UTF-8.
        $safeName = preg_replace('/[^\p{L}\p{N}\s\-_\.]/u', '', $file->name) ?: 'dokumen';
        $asciiName = preg_replace('/[^\x20-\x7E]/', '_', $safeName);
        $asciiName = str_replace(['\\', '"'], '_', $asciiName);

        return Storage::disk($disk)->response($file->path, $safeName . '.pdf', [
            'Content-Type'           => 'application/pdf',
            'Content-Disposition'    => $disposition . '; filename="' . $asciiName . '.pdf"; filename*=UTF-8\'\'' . rawurlencode($safeName . '.pdf'),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
