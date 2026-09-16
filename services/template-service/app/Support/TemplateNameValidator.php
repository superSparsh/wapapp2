<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Template;
use Illuminate\Support\Str;

final class TemplateNameValidator
{
    public static function normalizeCode(string $name): string
    {
        return Str::of($name)->trim()->lower()->replace(' ', '_')->toString();
    }

    public static function nameExistsForLine(string $name, ?int $whatsappLineId, ?int $exceptTemplateId = null): bool
    {
        if (trim($name) === '') {
            return false;
        }

        return Template::query()
            ->whereNull('deleted_at')
            ->whereRaw('LOWER(name) = ?', [Str::lower(trim($name))])
            ->where('whatsapp_line_id', $whatsappLineId)
            ->when($exceptTemplateId !== null, fn ($query) => $query->where('id', '!=', $exceptTemplateId))
            ->exists();
    }

    public static function codeExistsForLine(string $code, ?int $whatsappLineId, ?int $exceptTemplateId = null): bool
    {
        return self::nameExistsForLine($code, $whatsappLineId, $exceptTemplateId);
    }
}
