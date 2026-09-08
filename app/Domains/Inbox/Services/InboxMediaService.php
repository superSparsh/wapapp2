<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class InboxMediaService
{
    /**
     * @return array{path: string, url: string, mime: string, original_name: string}
     */
    public function store(UploadedFile $file): array
    {
        $disk = (string) config('whatsapp.media.disk', 'public');
        $directory = (string) config('whatsapp.media.directory', 'inbox/outbound');

        $path = $file->store($directory, $disk);

        return [
            'path' => $path,
            'url' => Storage::disk($disk)->url($path),
            'mime' => (string) ($file->getMimeType() ?? 'application/octet-stream'),
            'original_name' => (string) $file->getClientOriginalName(),
        ];
    }
}
