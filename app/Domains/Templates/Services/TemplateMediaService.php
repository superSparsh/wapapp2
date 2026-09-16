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
        // Always public so /storage/... and WhatsApp preview URLs work.
        $disk = (string) config('templates.header_media_disk', 'public');
        $directory = 'templates/headers';

        Storage::disk($disk)->makeDirectory($directory);

        $path = $file->store($directory, $disk);

        if ($path === false || $path === '') {
            throw new \RuntimeException('Failed to store header media.');
        }

        return [
            'path' => $path,
            // Prefer request-rooted URL over disk config APP_URL (avoids localhost 403
            // when the app is opened on Herd/Valet/another host).
            'url' => $this->publicUrl($path),
            'mime' => (string) ($file->getMimeType() ?? 'application/octet-stream'),
            'original_name' => (string) $file->getClientOriginalName(),
        ];
    }

    private function publicUrl(string $path): string
    {
        $relative = '/storage/'.ltrim($path, '/');

        try {
            return url($relative);
        } catch (\Throwable) {
            return $relative;
        }
    }
}
