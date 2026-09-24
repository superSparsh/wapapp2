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
     * Legacy parity: prefer public/flows/flow_{uuid}.json (url(/flows/...)).
     *
     * @param  array<string, mixed>  $metaJson
     */
    public function write(WhatsappFlow $flow, array $metaJson): string
    {
        $filename = 'flow_'.$flow->uuid.'.json';
        $payload = json_encode($metaJson, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        // 1) Legacy public/flows — CAMS FilePath = {APP_URL}/flows/flow_{uuid}.json
        $publicRelative = 'flows/'.$filename;
        if ($this->writeToDirectory(public_path('flows'), $filename, $payload)) {
            // Also mirror to storage for backups / storage:link consumers.
            $this->writeToDirectory(base_path('storage/app/public/flows'), $filename, $payload);

            return $publicRelative;
        }

        // 2) Central public storage — served at /storage/flows/...
        $storageRelative = 'storage/flows/'.$filename;
        if ($this->writeToDirectory(base_path('storage/app/public/flows'), $filename, $payload)) {
            return $storageRelative;
        }

        Log::warning('WhatsApp Flow JSON could not be written to a public directory', [
            'flow_id' => $flow->id,
            'uuid' => $flow->uuid,
            'tried' => [
                public_path('flows'),
                base_path('storage/app/public/flows'),
            ],
        ]);

        return $publicRelative;
    }

    /**
     * Absolute URL for CAMS UpdateFlowJSONAsset FilePath.
     *
     * Prefer a simple static /flows/... URL (legacy) when the file exists so CAMS
     * does not depend on tenancy middleware. Fall back to the live public-asset route.
     */
    public function publicUrl(string $relativePath, ?WhatsappFlow $flow = null): string
    {
        $relativePath = ltrim($relativePath, '/');
        $uuid = $flow?->uuid;
        $bust = '?v='.time();

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

        // Legacy-compatible static file (must be freshly written by write()).
        if ($uuid !== null && $uuid !== '' && File::exists(public_path('flows/flow_'.$uuid.'.json'))) {
            return url('flows/flow_'.$uuid.'.json').$bust;
        }

        if (str_starts_with($relativePath, 'flows/') && File::exists(public_path($relativePath))) {
            return url($relativePath).$bust;
        }

        if (str_starts_with($relativePath, 'storage/')) {
            return url($relativePath).$bust;
        }

        $basename = basename($relativePath);
        if ($basename !== '' && File::exists(base_path('storage/app/public/flows/'.$basename))) {
            return url('storage/flows/'.$basename).$bust;
        }

        // Live convert route (tenant path) — always fresh converter output.
        if (
            $uuid !== null
            && $uuid !== ''
            && tenancy()->initialized
            && filled(tenant('id'))
        ) {
            return route('whatsapp-flows.public-asset', [
                'tenant' => tenant('id'),
                'uuid' => $uuid,
            ]).$bust;
        }

        return url($relativePath).$bust;
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
