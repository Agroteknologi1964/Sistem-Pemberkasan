<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Services\FileService;
use Illuminate\Http\Request;

class FileController extends Controller
{
    public function __construct(private FileService $fileService) {}

    /**
     * Preview PDF inline di browser.
     */
    public function preview(Request $request, string $fileId)
    {
        $file = File::findOrFail($fileId);

        // Cek akses: user harus punya akses ke folder file ini
        $user = auth()->user();
        if (! $user->canAccessFolder($file->folder)) {
            abort(403, 'Akses ditolak.');
        }

        return $this->fileService->stream($file, download: false);
    }

    /**
     * Download file PDF.
     */
    public function download(string $fileId)
    {
        $file = File::findOrFail($fileId);

        $user = auth()->user();
        if (! $user->canAccessFolder($file->folder)) {
            abort(403, 'Akses ditolak.');
        }

        return $this->fileService->stream($file, download: true);
    }
}
