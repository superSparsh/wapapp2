<?php

declare(strict_types=1);

namespace App\Domains\Admin\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;

class MaintenanceModeService
{
    public const CACHE_KEY = 'platform.maintenance.state';

    public const CACHE_TTL_SECONDS = 30;

    /**
     * Modules that can keep running while customer UI is locked.
     *
     * @var array<string, array{label: string, description: string, default: bool}>
     */
    public const MODULES = [
        'inbound_webhooks' => [
            'label' => 'Inbound WhatsApp webhooks',
            'description' => 'Alibaba/Meta message & status uplink keeps accepting traffic.',
            'default' => true,
        ],
        'outbound_messages' => [
            'label' => 'Outbound WhatsApp sends',
            'description' => 'Queued free-form / template / media delivery jobs keep sending.',
            'default' => true,
        ],
        'chatbot' => [
            'label' => 'Chatbot flows',
            'description' => 'Auto-replies and delayed chatbot nodes keep executing.',
            'default' => true,
        ],
        'campaigns' => [
            'label' => 'Campaigns',
            'description' => 'Scheduled / due campaign sends continue processing.',
            'default' => true,
        ],
        'drip' => [
            'label' => 'Drip marketing',
            'description' => 'Drip automation steps keep running on schedule.',
            'default' => true,
        ],
        'templates_sync' => [
            'label' => 'Template sync',
            'description' => 'Meta template submit / status sync jobs keep running.',
            'default' => true,
        ],
        'outbound_webhooks' => [
            'label' => 'Customer outbound webhooks',
            'description' => 'Retries and deliveries to customer webhook endpoints.',
            'default' => true,
        ],
        'shopify' => [
            'label' => 'Shopify ingest',
            'description' => 'Shopify webhook processing continues.',
            'default' => true,
        ],
        'billing_jobs' => [
            'label' => 'Billing & wallet jobs',
            'description' => 'Razorpay sync, renewals, and wallet auto-recharge.',
            'default' => true,
        ],
        'customer_api' => [
            'label' => 'Customer API (v1)',
            'description' => 'Allow token API access while the dashboard is locked.',
            'default' => false,
        ],
        'reverb_realtime' => [
            'label' => 'Inbox realtime (Reverb)',
            'description' => 'Keep broadcasting inbox events (admin ops / monitoring).',
            'default' => true,
        ],
    ];

    public function state(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
            $stored = PlatformSetting::query()
                ->where('key', 'like', 'maintenance.%')
                ->pluck('value', 'key');

            $modules = [];
            foreach (self::MODULES as $key => $meta) {
                $settingKey = 'maintenance.modules.'.$key;
                $raw = $stored[$settingKey] ?? null;
                $modules[$key] = $raw === null
                    ? $meta['default']
                    : in_array((string) $raw, ['1', 'true', 'yes', 'on'], true);
            }

            return [
                'enabled' => in_array((string) ($stored['maintenance.enabled'] ?? '0'), ['1', 'true', 'yes', 'on'], true),
                'message' => (string) ($stored['maintenance.message'] ?? ''),
                'until' => filled($stored['maintenance.until'] ?? null) ? (string) $stored['maintenance.until'] : null,
                'modules' => $modules,
            ];
        });
    }

    public function enabled(): bool
    {
        $state = $this->state();

        if (! $state['enabled']) {
            return false;
        }

        if (filled($state['until'])) {
            try {
                if (now()->greaterThan(\Illuminate\Support\Carbon::parse($state['until']))) {
                    return false;
                }
            } catch (\Throwable) {
                // Ignore invalid until and keep enabled.
            }
        }

        return true;
    }

    public function message(): string
    {
        $message = trim((string) ($this->state()['message'] ?? ''));

        return $message !== ''
            ? $message
            : 'We are performing scheduled maintenance. The dashboard is temporarily unavailable. Messaging automations may still run in the background.';
    }

    public function until(): ?string
    {
        return $this->state()['until'] ?? null;
    }

    public function moduleEnabled(string $module): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        return (bool) ($this->state()['modules'][$module] ?? false);
    }

    /**
     * @param  array{
     *     enabled?: bool,
     *     message?: string|null,
     *     until?: string|null,
     *     modules?: array<string, bool>
     * }  $payload
     */
    public function save(array $payload): void
    {
        PlatformSetting::query()->updateOrCreate(
            ['key' => 'maintenance.enabled'],
            ['value' => ! empty($payload['enabled']) ? '1' : '0'],
        );

        PlatformSetting::query()->updateOrCreate(
            ['key' => 'maintenance.message'],
            ['value' => (string) ($payload['message'] ?? '')],
        );

        PlatformSetting::query()->updateOrCreate(
            ['key' => 'maintenance.until'],
            ['value' => (string) ($payload['until'] ?? '')],
        );

        $modules = is_array($payload['modules'] ?? null) ? $payload['modules'] : [];
        foreach (array_keys(self::MODULES) as $key) {
            PlatformSetting::query()->updateOrCreate(
                ['key' => 'maintenance.modules.'.$key],
                ['value' => ! empty($modules[$key]) ? '1' : '0'],
            );
        }

        Cache::forget(self::CACHE_KEY);
    }

    public function refresh(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
