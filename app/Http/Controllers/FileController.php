<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Services\DocumentAccessLogger;
use App\Services\FileService;
use Illuminate\Http\Request;

class FileController extends Controller
{
    public function __construct(
        private FileService $fileService,
        private DocumentAccessLogger $accessLogger,
    ) {}

    /**
     * Preview PDF inline di browser.
     */
    public function preview(Request $request, string $fileId)
    {
        $file = File::with('folder')->findOrFail($fileId);

        // Cek akses: user harus punya akses ke folder file ini.
        // canAccessFolder() menelusuri leluhur, jadi subfolder yang berada
        // di bawah folder yang di-assign juga lolos.
        $user = auth()->user();
        if (! $user->canAccessFolder($file->folder)) {
            abort(403, 'Akses ditolak.');
        }

        // Catat siapa membuka dokumen ini — jejak audit untuk surat penting.
        $this->accessLogger->record($file, $user, DocumentAccessLogger::EVENT_PREVIEW);

        return $this->fileService->stream($file, download: false);
    }

    /**
     * Download file PDF.
     */
    public function download(string $fileId)
    {
        $file = File::with('folder')->findOrFail($fileId);

        $user = auth()->user();
        if (! $user->canAccessFolder($file->folder)) {
            abort(403, 'Akses ditolak.');
        }

        // Unduhan meninggalkan salinan di luar kendali sistem — wajib tercatat.
        $this->accessLogger->record($file, $user, DocumentAccessLogger::EVENT_DOWNLOAD);

        return $this->fileService->stream($file, download: true);
    }
}
