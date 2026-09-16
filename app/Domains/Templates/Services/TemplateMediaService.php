<?php

declare(strict_types=1);

namespace App\Domains\Templates\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class TemplateMediaService
{
    /**
     * @return array{path: string, url: string, mime: string, original_name: string}
     */
    public function storeHeaderMedia(UploadedFile $file): array
    {
        // Always public so asset('storage/...') and WhatsApp preview URLs work.
        $disk = (string) config('templates.header_media_disk', 'public');
        $directory = 'templates/headers';
        $path = $file->store($directory, $disk);

        if ($path === false) {
            throw new \RuntimeException('Failed to store header media.');
        }

        return [
            'path' => $path,
            'url' => Storage::disk($disk)->url($path),
            'mime' => (string) ($file->getMimeType() ?? 'application/octet-stream'),
            'original_name' => (string) $file->getClientOriginalName(),
        ];
    }
}
