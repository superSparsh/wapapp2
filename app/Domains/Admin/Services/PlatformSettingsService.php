<?php

declare(strict_types=1);

namespace App\Domains\Admin\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Collection;

class PlatformSettingsService
{
    /** @var list<string> */
    public const KEYS = [
        'general.app_name',
        'general.support_email',
        'mailer.from_address',
        'mailer.from_name',
        'payment.razorpay_enabled',
        'payment.primary_gateway',
        'tax.enabled',
        'tax.default_rate',
        'tax.countries',
        'invoice.custom_template',
        'oauth.google_enabled',
        'oauth.google_client_id',
        'oauth.google_client_secret',
        'oauth.facebook_enabled',
        'oauth.facebook_client_id',
        'oauth.facebook_client_secret',
        'payment.razorpay_key',
        'payment.razorpay_secret',
        'payment.razorpay_webhook_secret',
        'payment.offline_instructions',
    ];

    /**
     * @return array<string, string|null>
     */
    public function all(): array
    {
        $stored = PlatformSetting::query()->pluck('value', 'key');
        $out = [];
        foreach (self::KEYS as $key) {
            $out[$key] = $stored[$key] ?? null;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function save(array $values): void
    {
        foreach (self::KEYS as $key) {
            if (! array_key_exists($key, $values)) {
                continue;
            }
            $value = $values[$key];
            PlatformSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) ($value ?? '')],
            );
        }
    }
}
