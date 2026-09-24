<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Chatbot builder media lives on the tenant public disk.
 * /storage/... symlinks point at central storage, so previews must use an
 * app route that streams from the tenant disk (same pattern as templates).
 */
class ChatbotMediaService
{
    /**
     * @return array{path: string, url: string, mime: string, original_name: string}
     */
    public function store(UploadedFile $file): array
    {
        $disk = 'public';
        $directory = 'chatbot/media';

        Storage::disk($disk)->makeDirectory($directory);

        $path = $file->store($directory, $disk);

        if ($path === false || $path === '') {
            throw new \RuntimeException('Failed to store chatbot media.');
        }

        return [
            'path' => $path,
            'url' => $this->previewUrl($path),
            'mime' => (string) ($file->getMimeType() ?? 'application/octet-stream'),
            'original_name' => (string) $file->getClientOriginalName(),
        ];
    }

    public function previewUrl(string $path): string
    {
        $path = ltrim($path, '/');

        return route('chatbot.media.show', ['path' => $path]);
    }

    public function stream(string $path): StreamedResponse
    {
        $path = $this->normalizePath($path);
        $disk = Storage::disk('public');

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

    private function normalizePath(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $path = preg_replace('#\.\./#', '', $path) ?? $path;

        if (! str_starts_with($path, 'chatbot/media/')) {
            abort(404);
        }

        return $path;
    }
}
