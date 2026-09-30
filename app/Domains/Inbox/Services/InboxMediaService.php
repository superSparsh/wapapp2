<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Inbox outbound media lives on the tenant public disk.
 * /storage/... serves central storage only - browsers must use the
 * auth-gated stream route (same pattern as templates / chatbot).
 */
class InboxMediaService
{
    /**
     * @return array{path: string, url: string, mime: string, original_name: string}
     */
    public function store(UploadedFile $file): array
    {
        $disk = (string) config('whatsapp.media.disk', 'public');
        $directory = (string) config('whatsapp.media.directory', 'inbox/outbound');

        Storage::disk($disk)->makeDirectory($directory);

        $path = $file->store($directory, $disk);

        if ($path === false || $path === '') {
            throw new \RuntimeException('Failed to store inbox media.');
        }

        return [
            'path' => $path,
            'url' => $this->previewUrl($path),
            'mime' => (string) ($file->getMimeType() ?? 'application/octet-stream'),
            'original_name' => (string) $file->getClientOriginalName(),
        ];
    }

    /**
     * In-app preview URL (tenant disk stream - no /storage symlink).
     */
    public function previewUrl(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        return route('inbox.media.show', ['path' => $path]);
    }

    /**
     * Convert a stored path or legacy /storage/... URL into a browser-usable URL.
     */
    public function displayUrl(?string $pathOrUrl, ?string $mediaPath = null): ?string
    {
        $mediaPath = $mediaPath !== null ? trim(str_replace('\\', '/', $mediaPath), '/') : '';
        if ($mediaPath !== '' && $this->isInboxOutboundPath($mediaPath)) {
            return $this->previewUrl($mediaPath);
        }

        $raw = trim((string) $pathOrUrl);
        if ($raw === '') {
            return null;
        }

        // Already our stream route.
        if (str_contains($raw, '/inbox/media/')) {
            return $raw;
        }

        // Legacy Storage::url() / absolute /storage/inbox/outbound/... (403 on tenant disks).
        if (preg_match('#(?:^|/)storage/(inbox/outbound/.+)$#', $raw, $matches) === 1) {
            return $this->previewUrl($matches[1]);
        }

        if ($this->isInboxOutboundPath($raw)) {
            return $this->previewUrl($raw);
        }

        return $raw;
    }

    public function stream(string $path): StreamedResponse
    {
        $path = $this->normalizePath($path);
        $disk = Storage::disk((string) config('whatsapp.media.disk', 'public'));

        if (! $disk->exists($path)) {
            abort(404);
        }

        $mime = (string) ($disk->mimeType($path) ?: 'application/octet-stream');

        return response()->stream(function () use ($disk, $path): void {
            $stream = $disk->readStream($path);
            if (! is_resource($stream)) {
                return;
            }

            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function normalizePath(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $path = preg_replace('#\.\./#', '', $path) ?? $path;

        if (! $this->isInboxOutboundPath($path)) {
            abort(404);
        }

        return $path;
    }

    private function isInboxOutboundPath(string $path): bool
    {
        $directory = trim((string) config('whatsapp.media.directory', 'inbox/outbound'), '/');

        return str_starts_with($path, $directory.'/');
    }
}
