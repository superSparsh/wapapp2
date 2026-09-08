<?php

declare(strict_types=1);

namespace App\Domains\WhatsappFlow\Services;

use App\Models\WhatsappFlow;
use Illuminate\Support\Facades\File;

class WhatsappFlowAssetService
{
    /**
     * @param  array<string, mixed>  $metaJson
     */
    public function write(WhatsappFlow $flow, array $metaJson): string
    {
        $directory = public_path('flows');
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $relativePath = 'flows/flow_'.$flow->uuid.'.json';
        $absolutePath = public_path($relativePath);

        File::put($absolutePath, json_encode($metaJson, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        return $relativePath;
    }

    public function publicUrl(string $relativePath): string
    {
        return url($relativePath);
    }
}
