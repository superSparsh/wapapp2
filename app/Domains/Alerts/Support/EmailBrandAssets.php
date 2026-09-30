<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Support;

/**
 * Shared branding assets for transactional / ops emails.
 * Outlook often blocks remote images — prefer CID embeds over asset() URLs.
 */
final class EmailBrandAssets
{
    /** Must include "@" for Symfony Mime Content-ID validation. */
    public const LOGO_CID = 'wapapp-logo@wapapp.local';

    public static function logoPath(): string
    {
        return public_path('images/tittu-logo.jpeg');
    }

    public static function logoExists(): bool
    {
        return is_file(self::logoPath());
    }

    /**
     * CID URL for embedded logo (works in Outlook without "download images").
     */
    public static function logoCidUrl(): string
    {
        return 'cid:'.self::LOGO_CID;
    }

    /**
     * Absolute public URL fallback when embedding is unavailable.
     */
    public static function logoPublicUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/images/tittu-logo.jpeg';
    }
}
