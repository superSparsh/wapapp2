<?php

declare(strict_types=1);

namespace App\Domains\Templates\Support;

use App\Models\Template;
use Illuminate\Support\Str;

final class TemplateNameValidator
{
    public static function normalizeCode(string $name): string
    {
        return Str::of($name)->trim()->lower()->replace(' ', '_')->toString();
    }

    /**
     * Whether an active (non-soft-deleted) template already uses this local name on the line.
     * (Alibaba TemplateCode lives in `code` and must not be confused with the local name.)
     */
    public static function nameExistsForLine(string $name, ?int $whatsappLineId, ?int $exceptTemplateId = null): bool
    {
        $normalized = self::normalizeCode($name);
        if ($normalized === '') {
            return false;
        }

        return Template::query()
            ->whereNull('deleted_at')
            ->where('whatsapp_line_id', $whatsappLineId)
            ->where(function ($query) use ($name, $normalized): void {
                $query->whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($name))])
                    // Legacy drafts that stored the snake_case name in `code`
                    ->orWhere('code', $normalized);
            })
            ->when($exceptTemplateId !== null, fn ($query) => $query->where('id', '!=', $exceptTemplateId))
            ->exists();
    }

    /**
     * @deprecated Prefer nameExistsForLine — `code` is reserved for CAMS TemplateCode.
     */
    public static function codeExistsForLine(string $code, ?int $whatsappLineId, ?int $exceptTemplateId = null): bool
    {
        return self::nameExistsForLine($code, $whatsappLineId, $exceptTemplateId);
    }
}
