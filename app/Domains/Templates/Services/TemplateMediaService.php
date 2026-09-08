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
        $disk = (string) config('filesystems.default', 'public');
        $directory = 'templates/headers';
        $path = $file->store($directory, $disk);

        return [
            'path' => $path,
            'url' => Storage::disk($disk)->url($path),
            'mime' => (string) ($file->getMimeType() ?? 'application/octet-stream'),
            'original_name' => (string) $file->getClientOriginalName(),
        ];
    }
}
