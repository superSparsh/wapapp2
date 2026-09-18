<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Services;

use App\Domains\LegacyMigration\Support\LegacyConnection;
use App\Models\PlatformSetting;

class LegacySettingsImportService
{
    /**
     * Direct name → platform_settings.key map for known admin settings.
     *
     * @var array<string, string>
     */
    public const KEY_MAP = [
        'site_name' => 'general.app_name',
        'mailer.from.address' => 'mailer.from_address',
        'mailer.from.name' => 'mailer.from_name',
        'cashier.razorpay.key_id' => 'payment.razorpay_key',
        'cashier.razorpay.key_secret' => 'payment.razorpay_secret',
        'cashier.razorpay.webhook_secret' => 'payment.razorpay_webhook_secret',
        'cashier.offline.payment_instruction' => 'payment.offline_instructions',
        'invoice.custom_template' => 'invoice.custom_template',
        'oauth.google_enabled' => 'oauth.google_enabled',
        'oauth.google_client_id' => 'oauth.google_client_id',
        'oauth.google_client_secret' => 'oauth.google_client_secret',
        'oauth.facebook_enabled' => 'oauth.facebook_enabled',
        'oauth.facebook_client_id' => 'oauth.facebook_client_id',
        'oauth.facebook_client_secret' => 'oauth.facebook_client_secret',
        'wallet_balance_unit' => 'wallet.balance_unit',
        'conversion_price' => 'wallet.conversion_price',
    ];

    public function __construct(
        private readonly LegacyConnection $legacy,
    ) {}

    /**
     * @return array{mapped: int, derived: int, extras: int, skipped: int}
     */
    public function import(bool $dryRun = false, bool $includeExtras = false): array
    {
        $this->legacy->assertReady();

        if (! $this->legacy->tableExists('settings')) {
            throw new \RuntimeException('Legacy table [settings] was not found.');
        }

        $stats = ['mapped' => 0, 'derived' => 0, 'extras' => 0, 'skipped' => 0];
        $legacy = $this->loadLegacySettings();

        $payload = [];

        foreach (self::KEY_MAP as $legacyName => $platformKey) {
            if (! array_key_exists($legacyName, $legacy)) {
                $stats['skipped']++;

                continue;
            }

            $payload[$platformKey] = $this->normalizeMappedValue($platformKey, $legacy[$legacyName]);
            $stats['mapped']++;
        }

        $derived = $this->deriveFromCompositeSettings($legacy);
        foreach ($derived as $key => $value) {
            if (! array_key_exists($key, $payload) || $payload[$key] === null || $payload[$key] === '') {
                $payload[$key] = $value;
                $stats['derived']++;
            }
        }

        if ($includeExtras) {
            foreach ($legacy as $name => $value) {
                if (isset(self::KEY_MAP[$name]) || in_array($name, ['tax', 'gateways'], true)) {
                    continue;
                }

                $extraKey = 'legacy.'.$name;
                $payload[$extraKey] = is_scalar($value) || $value === null
                    ? (string) ($value ?? '')
                    : json_encode($value);
                $stats['extras']++;
            }
        }

        if ($dryRun) {
            return $stats;
        }

        foreach ($payload as $key => $value) {
            PlatformSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) ($value ?? '')],
            );
        }

        return $stats;
    }

    /**
     * @return array<string, string|null>
     */
    private function loadLegacySettings(): array
    {
        $nameCol = $this->legacy->hasColumn('settings', 'name') ? 'name' : 'key';
        $valueCol = $this->legacy->hasColumn('settings', 'value') ? 'value' : 'val';

        $out = [];
        $this->legacy->db()->table('settings')->orderBy('id')->get()->each(function ($row) use (&$out, $nameCol, $valueCol): void {
            $name = trim((string) ($row->{$nameCol} ?? ''));
            if ($name === '') {
                return;
            }
            $out[$name] = $row->{$valueCol} ?? null;
        });

        return $out;
    }

    private function normalizeMappedValue(string $platformKey, mixed $raw): string
    {
        $value = is_string($raw) ? trim($raw) : (string) ($raw ?? '');

        if (str_ends_with($platformKey, '_enabled')) {
            return $this->truthy($value) ? '1' : '0';
        }

        if ($platformKey === 'wallet.balance_unit') {
            $unit = strtolower($value);

            return in_array($unit, ['usd', 'inr'], true) ? $unit : 'inr';
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @return array<string, string>
     */
    private function deriveFromCompositeSettings(array $legacy): array
    {
        $out = [];

        $taxRaw = $legacy['tax'] ?? null;
        if (is_string($taxRaw) && $taxRaw !== '') {
            $tax = json_decode($taxRaw, true);
            if (is_array($tax)) {
                $out['tax.enabled'] = ! empty($tax['enabled']) || ! empty($tax['enable']) ? '1' : '0';
                if (isset($tax['rate']) || isset($tax['default_rate'])) {
                    $out['tax.default_rate'] = (string) ($tax['rate'] ?? $tax['default_rate']);
                }
                if (isset($tax['countries']) && is_array($tax['countries'])) {
                    $out['tax.countries'] = json_encode(array_values($tax['countries']));
                } elseif (isset($tax['countries']) && is_string($tax['countries'])) {
                    $out['tax.countries'] = $tax['countries'];
                }
            }
        }

        $gatewaysRaw = $legacy['gateways'] ?? null;
        $gateways = [];
        if (is_string($gatewaysRaw) && $gatewaysRaw !== '') {
            $decoded = json_decode($gatewaysRaw, true);
            $gateways = is_array($decoded) ? $decoded : [];
        } elseif (is_array($gatewaysRaw)) {
            $gateways = $gatewaysRaw;
        }

        $hasRazorpay = collect($gateways)->contains(function ($item): bool {
            $value = strtolower((string) (is_array($item) ? ($item['name'] ?? $item['type'] ?? '') : $item));

            return str_contains($value, 'razorpay');
        });

        if ($hasRazorpay || filled($legacy['cashier.razorpay.key_id'] ?? null)) {
            $out['payment.razorpay_enabled'] = '1';
            $out['payment.primary_gateway'] = 'razorpay';
        }

        return $out;
    }

    private function truthy(string $value): bool
    {
        return in_array(strtolower($value), ['1', 'yes', 'true', 'on', 'active', 'enabled'], true);
    }
}
