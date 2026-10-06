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

    /**
     * Resolve an on-disk path for a tutorial filename (case-insensitive on Linux).
     */
    public function resolvePath(string $filename): ?string
    {
        $filename = basename(trim($filename));

        if ($filename === '') {
            return null;
        }

        $path = $this->pathFor($filename);

        if (File::isFile($path)) {
            return $path;
        }

        $directory = $this->directory();

        if (! File::isDirectory($directory)) {
            return null;
        }

        foreach (File::files($directory) as $file) {
            if (strcasecmp($file->getFilename(), $filename) === 0) {
                return $file->getPathname();
            }
        }

        return null;
    }

    /**
     * Actual on-disk basename when a case-insensitive match exists.
     */
    public function canonicalFilename(string $filename): ?string
    {
        $resolved = $this->resolvePath($filename);

        return $resolved !== null ? basename($resolved) : null;
    }

    public function exists(string $filename): bool
    {
        return $this->resolvePath($filename) !== null;
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

        // Case-only rename clash (Video.mp4 vs video.mp4) on case-sensitive disks.
        $existing = $this->resolvePath($filename);
        if ($existing !== null && realpath($existing) !== realpath($destination)) {
            File::delete($existing);
        }

        $file->move($this->directory(), $filename);

        // Keep the previous file on disk as a frontend fallback until it is
        // explicitly deleted. Progressive tutorial updates rely on this.

        return $filename;
    }

    public function delete(string $filename): bool
    {
        $filename = basename(trim($filename));

        if ($filename === '') {
            return false;
        }

        $path = $this->resolvePath($filename);

        if ($path === null) {
            return false;
        }

        return File::delete($path);
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
