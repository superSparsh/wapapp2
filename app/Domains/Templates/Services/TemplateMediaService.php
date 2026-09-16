<?php

declare(strict_types=1);

namespace App\Domains\Templates\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        try {
            Storage::disk($disk)->setVisibility($path, 'public');
        } catch (\Throwable) {
            // Some drivers ignore visibility; file is still readable via app serve route.
        }

        return [
            'path' => $path,
            // App-served URL works even when public/storage symlink is missing/broken.
            'url' => $this->previewUrl($path),
            'mime' => (string) ($file->getMimeType() ?? 'application/octet-stream'),
            'original_name' => (string) $file->getClientOriginalName(),
        ];
    }

    /**
     * In-app preview URL (auth-gated Laravel route — no symlink required).
     */
    public function previewUrl(string $path): string
    {
        $path = ltrim($path, '/');

        return route('templates.media.show', ['path' => $path]);
    }

    /**
     * Public disk URL for external consumers (WhatsApp / CAMS). Relative to avoid APP_URL host mismatch.
     */
    public function publicUrl(string $path): string
    {
        return '/storage/'.ltrim($path, '/');
    }

    /**
     * Absolute public URL for provider APIs.
     */
    public function absolutePublicUrl(string $path): string
    {
        $relative = $this->publicUrl($path);

        try {
            return url($relative);
        } catch (\Throwable) {
            return $relative;
        }
    }

    public function stream(string $path): StreamedResponse
    {
        $path = $this->normalizePath($path);
        $disk = Storage::disk((string) config('templates.header_media_disk', 'public'));

        if (! $disk->exists($path)) {
            abort(404);
        }

        return $disk->response($path);
    }

    public function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = ltrim($path, '/');

        if ($path === '' || str_contains($path, '..')) {
            abort(404);
        }

        return $path;
    }
}
