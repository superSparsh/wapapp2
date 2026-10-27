<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Services;

use App\Enums\MessageDirection;
use App\Enums\MessageType;
use App\Models\Message;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Inbox media lives on the tenant public disk.
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
        $directory = $this->outboundDirectory();

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
     * Download a remote inbound media URL onto the tenant disk.
     *
     * @return array{path: string, url: string, mime: string, original_name: string}
     */
    public function storeFromRemoteUrl(string $url, ?string $preferredName = null, ?string $mimeHint = null): array
    {
        $url = trim($url);
        if (preg_match('#^https?://#i', $url) !== 1) {
            throw new \InvalidArgumentException('Inbound media URL must be http(s).');
        }

        $response = Http::timeout(60)
            ->withHeaders(['User-Agent' => 'wapapp-inbox-media/1.0'])
            ->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException('Unable to download inbound media (HTTP '.$response->status().').');
        }

        $contents = $response->body();
        if (! is_string($contents) || $contents === '') {
            throw new \RuntimeException('Inbound media download returned an empty body.');
        }

        $maxBytes = max(1, (int) config('whatsapp.media.max_size_kb', 14336)) * 1024;
        if (strlen($contents) > $maxBytes) {
            throw new \RuntimeException('Inbound media exceeds the configured size limit.');
        }

        $mime = trim((string) ($response->header('Content-Type') ?: ($mimeHint ?? 'application/octet-stream')));
        $mime = strtolower(explode(';', $mime)[0] ?? $mime);
        if ($mime === '') {
            $mime = 'application/octet-stream';
        }

        $originalName = $this->resolveFileName($url, $preferredName, $mime, $response->header('Content-Disposition'));
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        if ($extension === '') {
            $extension = $this->extensionForMime($mime) ?? 'bin';
            $originalName .= '.'.$extension;
        }

        $directory = $this->inboundDirectory();
        $diskName = (string) config('whatsapp.media.disk', 'public');
        $disk = Storage::disk($diskName);
        $disk->makeDirectory($directory);

        $path = $directory.'/'.Str::uuid()->toString().'.'.strtolower($extension);
        if (! $disk->put($path, $contents)) {
            throw new \RuntimeException('Failed to store inbound media.');
        }

        return [
            'path' => $path,
            'url' => $this->previewUrl($path),
            'mime' => $mime,
            'original_name' => $originalName,
        ];
    }

    /**
     * Persist remote inbound media for a message onto the tenant disk.
     * Keeps the original URL as media_url_remote and points display to media_path.
     */
    public function persistInboundMessageMedia(Message $message): bool
    {
        if ($message->direction !== MessageDirection::Inbound) {
            return false;
        }

        if (! in_array($message->message_type, [
            MessageType::Image,
            MessageType::Video,
            MessageType::Audio,
            MessageType::Document,
            MessageType::Sticker,
        ], true)) {
            return false;
        }

        $metadata = is_array($message->metadata) ? $message->metadata : [];
        $existingPath = trim((string) ($metadata['media_path'] ?? ''));
        $disk = Storage::disk((string) config('whatsapp.media.disk', 'public'));

        if ($existingPath !== '' && $this->isInboxMediaPath($existingPath) && $disk->exists($existingPath)) {
            return false;
        }

        $remoteUrl = $this->resolveRemoteMediaUrl($metadata);
        if ($remoteUrl === null) {
            return false;
        }

        $stored = $this->storeFromRemoteUrl(
            $remoteUrl,
            isset($metadata['file_name']) ? (string) $metadata['file_name'] : null,
            isset($metadata['file_type']) ? (string) $metadata['file_type'] : null,
        );

        $metadata['media_url_remote'] = $remoteUrl;
        $metadata['media_path'] = $stored['path'];
        $metadata['media_url_local'] = $stored['url'];
        $metadata['file_name'] = $stored['original_name'];
        $metadata['file_type'] = $stored['mime'];

        $message->forceFill(['metadata' => $metadata])->save();

        return true;
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
        if ($mediaPath !== '' && $this->isInboxMediaPath($mediaPath)) {
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

        // Legacy Storage::url() / absolute /storage/inbox/... (403 on tenant disks).
        if (preg_match('#(?:^|/)storage/(inbox/(?:outbound|inbound)/.+)$#', $raw, $matches) === 1) {
            return $this->previewUrl($matches[1]);
        }

        if ($this->isInboxMediaPath($raw)) {
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

        if (! $this->isInboxMediaPath($path)) {
            abort(404);
        }

        return $path;
    }

    public function isInboxMediaPath(string $path): bool
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        return str_starts_with($path, $this->outboundDirectory().'/')
            || str_starts_with($path, $this->inboundDirectory().'/');
    }

    public function outboundDirectory(): string
    {
        return trim((string) config('whatsapp.media.directory', 'inbox/outbound'), '/');
    }

    public function inboundDirectory(): string
    {
        return trim((string) config('whatsapp.media.inbound_directory', 'inbox/inbound'), '/');
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function resolveRemoteMediaUrl(array $metadata): ?string
    {
        foreach (['media_url_remote', 'media_url', 'media_url_local'] as $key) {
            $value = trim((string) ($metadata[$key] ?? ''));
            if ($value === '') {
                continue;
            }
            if (str_contains($value, '/inbox/media/')) {
                continue;
            }
            if ($this->isInboxMediaPath($value)) {
                continue;
            }
            if (preg_match('#^https?://#i', $value) === 1) {
                return $value;
            }
        }

        if (is_array($metadata['raw_message'] ?? null)) {
            $raw = $metadata['raw_message'];
            foreach (['link', 'url', 'media_url'] as $key) {
                $value = trim((string) ($raw[$key] ?? ''));
                if ($value !== '' && preg_match('#^https?://#i', $value) === 1) {
                    return $value;
                }
            }
        }

        return null;
    }

    private function resolveFileName(string $url, ?string $preferredName, string $mime, ?string $contentDisposition): string
    {
        $preferredName = trim((string) $preferredName);
        if ($preferredName !== '') {
            return basename($preferredName);
        }

        if (is_string($contentDisposition) && preg_match('/filename\*?=(?:UTF-8\'\')?"?([^\";]+)"?/i', $contentDisposition, $matches) === 1) {
            $fromHeader = trim(rawurldecode($matches[1]));
            if ($fromHeader !== '') {
                return basename($fromHeader);
            }
        }

        $fromUrl = basename((string) (parse_url($url, PHP_URL_PATH) ?: ''));
        if ($fromUrl !== '' && $fromUrl !== '/' && str_contains($fromUrl, '.')) {
            return $fromUrl;
        }

        $ext = $this->extensionForMime($mime) ?? 'bin';

        return 'inbound-media.'.$ext;
    }

    private function extensionForMime(string $mime): ?string
    {
        return match (strtolower($mime)) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'video/mp4' => 'mp4',
            'video/3gpp' => '3gp',
            'audio/ogg', 'audio/opus' => 'ogg',
            'audio/mpeg' => 'mp3',
            'audio/aac' => 'aac',
            'audio/amr' => 'amr',
            'application/pdf' => 'pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'text/plain' => 'txt',
            default => null,
        };
    }
}
