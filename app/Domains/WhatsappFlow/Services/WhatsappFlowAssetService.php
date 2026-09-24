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
     * Persist a public-facing asset reference for CAMS FilePath.
     * Local public/flows cache is best-effort (servers often lock public/).
     * CAMS fetches via the tenant public asset route (DB-backed).
     *
     * @param  array<string, mixed>  $metaJson
     */
    public function write(WhatsappFlow $flow, array $metaJson): string
    {
        $relativePath = 'flows/flow_'.$flow->uuid.'.json';

        try {
            $this->writePublicCache($relativePath, $metaJson);
        } catch (Throwable $exception) {
            Log::warning('WhatsApp Flow public/flows cache write skipped', [
                'flow_id' => $flow->id,
                'path' => $relativePath,
                'error' => $exception->getMessage(),
            ]);
        }

        return $relativePath;
    }

    /**
     * Absolute URL CAMS can download (no auth). Prefer DB-backed route over /flows/*.json.
     */
    public function publicUrl(string $relativePath): string
    {
        $relativePath = ltrim($relativePath, '/');

        if (
            preg_match('/flow_([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})\.json$/i', $relativePath, $matches)
            && tenancy()->initialized
            && filled(tenant('id'))
        ) {
            return route('whatsapp-flows.public-asset', [
                'tenant' => tenant('id'),
                'uuid' => $matches[1],
            ]);
        }

        // Legacy static files under public/flows/
        return url($relativePath);
    }

    /**
     * @param  array<string, mixed>  $metaJson
     */
    private function writePublicCache(string $relativePath, array $metaJson): void
    {
        $directory = public_path('flows');

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0775, true);
        }

        if (! is_writable($directory)) {
            @chmod($directory, 0775);
        }

        if (! is_writable($directory)) {
            throw new \RuntimeException("Directory not writable: {$directory}");
        }

        $absolutePath = public_path($relativePath);
        File::put($absolutePath, json_encode($metaJson, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        @chmod($absolutePath, 0664);
    }
}
