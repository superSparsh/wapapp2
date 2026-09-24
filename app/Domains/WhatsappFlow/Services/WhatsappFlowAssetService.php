<?php

declare(strict_types=1);

namespace App\Domains\WhatsappFlow\Services;

use App\Models\WhatsappFlow;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsappFlowAssetService
{
    /**
     * Persist flow JSON where CAMS can download it via a public URL.
     * Prefer central storage/app/public (usually writable by php-fpm) over public/flows.
     *
     * @param  array<string, mixed>  $metaJson
     */
    public function write(WhatsappFlow $flow, array $metaJson): string
    {
        $filename = 'flow_'.$flow->uuid.'.json';
        $payload = json_encode($metaJson, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        // 1) Central public storage — served at /storage/flows/... (storage:link)
        $storageRelative = 'storage/flows/'.$filename;
        if ($this->writeToDirectory(base_path('storage/app/public/flows'), $filename, $payload)) {
            return $storageRelative;
        }

        // 2) Legacy public/flows — served at /flows/...
        $publicRelative = 'flows/'.$filename;
        if ($this->writeToDirectory(public_path('flows'), $filename, $payload)) {
            return $publicRelative;
        }

        Log::warning('WhatsApp Flow JSON could not be written to a public directory', [
            'flow_id' => $flow->id,
            'uuid' => $flow->uuid,
            'tried' => [
                base_path('storage/app/public/flows'),
                public_path('flows'),
            ],
        ]);

        // Path still recorded so publicUrl() can fall back to the DB-backed route.
        return $publicRelative;
    }

    /**
     * Absolute URL for CAMS UpdateFlowJSONAsset FilePath.
     *
     * Prefer the tenant public-asset route so CAMS always downloads freshly converted
     * JSON (not a stale static file from before a converter fix).
     */
    public function publicUrl(string $relativePath, ?WhatsappFlow $flow = null): string
    {
        $relativePath = ltrim($relativePath, '/');
        $uuid = $flow?->uuid;

        if ($uuid === null || $uuid === '') {
            if (
                preg_match(
                    '/flow_([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})\.json$/i',
                    $relativePath,
                    $matches,
                )
            ) {
                $uuid = $matches[1];
            }
        }

        if (
            $uuid !== null
            && $uuid !== ''
            && tenancy()->initialized
            && filled(tenant('id'))
        ) {
            return route('whatsapp-flows.public-asset', [
                'tenant' => tenant('id'),
                'uuid' => $uuid,
            ]).'?v='.time();
        }

        // storage/flows/... → /storage/flows/...
        if (str_starts_with($relativePath, 'storage/')) {
            return url($relativePath).'?v='.time();
        }

        // Legacy / newly written public/flows file
        if (File::exists(public_path($relativePath))) {
            return url($relativePath).'?v='.time();
        }

        // storage-backed file even if json_asset_path still uses legacy "flows/..." shape
        $basename = basename($relativePath);
        if ($basename !== '' && File::exists(base_path('storage/app/public/flows/'.$basename))) {
            return url('storage/flows/'.$basename).'?v='.time();
        }

        return url($relativePath).'?v='.time();
    }

    private function writeToDirectory(string $directory, string $filename, string $payload): bool
    {
        try {
            if (! File::isDirectory($directory)) {
                File::makeDirectory($directory, 0775, true);
            }

            if (! is_writable($directory)) {
                @chmod($directory, 0775);
            }

            if (! is_writable($directory)) {
                return false;
            }

            $absolute = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$filename;
            File::put($absolute, $payload);
            @chmod($absolute, 0664);

            return File::exists($absolute);
        } catch (Throwable $exception) {
            Log::warning('WhatsApp Flow asset directory write failed', [
                'directory' => $directory,
                'filename' => $filename,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
