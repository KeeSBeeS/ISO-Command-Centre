<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EncryptedAttachmentStorage
{
    public function store(UploadedFile $file, string $folder): array
    {
        $folder = trim($folder, '/');
        $randomName = (string) Str::uuid() . '-' . Str::random(16) . '.bin';
        $path = 'encrypted-attachments/' . $folder . '/' . $randomName;

        $payload = Crypt::encryptString(base64_encode(file_get_contents($file->getRealPath())));
        Storage::put($path, $payload);

        return [
            'path' => $path,
            'storage_filename' => $randomName,
            'disk' => config('filesystems.default', 'local'),
            'encrypted' => true,
            'version' => 'v2',
            'size_bytes' => $file->getSize(),
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
        ];
    }

    public function downloadResponse(object $document, string $notFoundMessage = 'Document file not found.')
    {
        $path = $document->file_path;
        if (!$path || !Storage::exists($path)) {
            abort(404, $notFoundMessage);
        }

        $isEncrypted = (bool) ($document->is_encrypted ?? false);
        $filename = $document->original_filename ?: 'document-download';
        $mime = $document->mime_type ?: 'application/octet-stream';

        if (!$isEncrypted) {
            return Storage::download($path, $filename);
        }

        $encryptedPayload = Storage::get($path);
        $plain = base64_decode(Crypt::decryptString($encryptedPayload), true);
        if ($plain === false) {
            abort(500, 'Encrypted document could not be decoded.');
        }

        return response($plain, 200, [
            'Content-Type' => $mime,
            'Content-Length' => strlen($plain),
            'Content-Disposition' => 'attachment; filename="' . addslashes($filename) . '"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }
}
