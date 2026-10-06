<?php

declare(strict_types=1);

namespace App\Domains\HelpCenter\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

class TutorialVideoStorage
{
    public function directory(): string
    {
        $directory = (string) config('help-center.video_path');

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        return $directory;
    }

    public function pathFor(string $filename): string
    {
        return $this->directory().DIRECTORY_SEPARATOR.basename($filename);
    }

    public function exists(string $filename): bool
    {
        $filename = trim($filename);

        if ($filename === '') {
            return false;
        }

        return File::isFile($this->pathFor($filename));
    }

    /**
     * Store an uploaded tutorial video. Overwrites the target filename if it already exists.
     * When $previousFilename differs from the new name, the old file is deleted after save.
     */
    public function store(UploadedFile $file, ?string $preferredFilename = null, ?string $previousFilename = null): string
    {
        $filename = $this->resolveFilename($file, $preferredFilename);
        $destination = $this->pathFor($filename);

        if (File::isFile($destination)) {
            File::delete($destination);
        }

        $file->move($this->directory(), $filename);

        $previousFilename = $previousFilename !== null ? basename(trim($previousFilename)) : null;
        if (
            $previousFilename !== null
            && $previousFilename !== ''
            && strcasecmp($previousFilename, $filename) !== 0
            && $this->exists($previousFilename)
        ) {
            $this->delete($previousFilename);
        }

        return $filename;
    }

    public function delete(string $filename): bool
    {
        $filename = basename(trim($filename));

        if ($filename === '' || ! $this->exists($filename)) {
            return false;
        }

        return File::delete($this->pathFor($filename));
    }

    private function resolveFilename(UploadedFile $file, ?string $preferredFilename): string
    {
        $preferredFilename = trim((string) $preferredFilename);

        if ($preferredFilename !== '') {
            $filename = basename($preferredFilename);
            if (! str_contains($filename, '.')) {
                $filename .= '.'.$this->extension($file);
            }

            return $this->ensureVideoExtension($filename, $file);
        }

        $original = basename((string) $file->getClientOriginalName());
        $original = preg_replace('/[^\w.\- &()[\]]+/u', '_', $original) ?: 'tutorial.mp4';

        return $this->ensureVideoExtension($original, $file);
    }

    private function ensureVideoExtension(string $filename, UploadedFile $file): string
    {
        if (preg_match('/\.(mp4|webm|ogg|mov)$/i', $filename) === 1) {
            return $filename;
        }

        return pathinfo($filename, PATHINFO_FILENAME).'.'.$this->extension($file);
    }

    private function extension(UploadedFile $file): string
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());

        return in_array($ext, ['mp4', 'webm', 'ogg', 'mov'], true) ? $ext : 'mp4';
    }
}
