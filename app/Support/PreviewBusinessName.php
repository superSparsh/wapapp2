<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\WhatsappLine;

final class PreviewBusinessName
{
    public static function resolve(): string
    {
        $lineName = WhatsappLine::query()
            ->orderByDesc('is_default')
            ->value('display_name');

        if (filled($lineName)) {
            return (string) $lineName;
        }

        $companyName = trim((string) (tenant('company_name') ?? ''));

        return $companyName !== '' ? $companyName : 'Your Business';
    }
}
