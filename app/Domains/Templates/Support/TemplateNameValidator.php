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

        $activeExists = Template::query()
            ->whereNull('deleted_at')
            ->where('whatsapp_line_id', $whatsappLineId)
            ->where(function ($query) use ($name, $normalized): void {
                $query->whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($name))])
                    // Legacy drafts that stored the snake_case name in `code`
                    ->orWhere('code', $normalized);
            })
            ->when($exceptTemplateId !== null, fn ($query) => $query->where('id', '!=', $exceptTemplateId))
            ->exists();

        if ($activeExists) {
            return true;
        }

        return self::nameBlockedByMetaCooldown($name, $whatsappLineId, $exceptTemplateId);
    }

    /**
     * Meta blocks recreating the same template name + language for ~4 weeks after delete.
     * Soft-deleted local rows with a real CAMS TemplateCode (or recent sync) must block reuse.
     */
    public static function nameBlockedByMetaCooldown(string $name, ?int $whatsappLineId, ?int $exceptTemplateId = null): bool
    {
        $trimmed = strtolower(trim($name));
        if ($trimmed === '') {
            return false;
        }

        $candidates = Template::onlyTrashed()
            ->where('whatsapp_line_id', $whatsappLineId)
            ->whereRaw('LOWER(TRIM(name)) = ?', [$trimmed])
            ->where('deleted_at', '>=', now()->subWeeks(4))
            ->when($exceptTemplateId !== null, fn ($query) => $query->where('id', '!=', $exceptTemplateId))
            ->get(['id', 'payload', 'synced_at', 'deleted_at']);

        foreach ($candidates as $template) {
            $archived = data_get($template->payload, 'meta.archived_code');
            if (CamsTemplateIdentity::isProviderCode(is_string($archived) ? $archived : null)) {
                return true;
            }

            if ($template->synced_at !== null) {
                return true;
            }
        }

        return false;
    }

    public static function metaCooldownMessage(): string
    {
        return 'This template name was recently deleted on WhatsApp. Meta blocks reusing the same name with English (UK) for up to 4 weeks. Choose a new name, or wait and try again.';
    }

    /**
     * @deprecated Prefer nameExistsForLine — `code` is reserved for CAMS TemplateCode.
     */
    public static function codeExistsForLine(string $code, ?int $whatsappLineId, ?int $exceptTemplateId = null): bool
    {
        return self::nameExistsForLine($code, $whatsappLineId, $exceptTemplateId);
    }
}
