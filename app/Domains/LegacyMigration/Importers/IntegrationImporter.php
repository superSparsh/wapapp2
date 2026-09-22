<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Importers;

use App\Domains\LegacyMigration\DTO\LegacyCustomerSnapshot;
use App\Domains\LegacyMigration\Support\LegacyConnection;
use App\Domains\LegacyMigration\Support\MigrationIdMap;
use App\Domains\LegacyMigration\Support\MigrationReport;
use App\Domains\ThirdParty\Enums\IntegrationStatus;
use App\Domains\ThirdParty\Models\CalendlyIntegration;
use App\Domains\ThirdParty\Models\GoogleCalendarIntegration;
use App\Domains\ThirdParty\Models\ShopifyIntegration;
use App\Models\Tenant;
use App\Models\User;

final class IntegrationImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyConnection $legacy,
    ) {}

    public function key(): string
    {
        return 'integrations';
    }

    public function import(
        LegacyCustomerSnapshot $customer,
        Tenant $tenant,
        MigrationIdMap $ids,
        MigrationReport $report,
        bool $dryRun = false,
    ): void {
        $userId = User::query()->orderBy('id')->value('id');
        if ($userId === null) {
            $report->warn('No tenant user found; skipped integrations.');

            return;
        }

        $this->importShopify($customer, (int) $userId, $report, $dryRun);
        $this->importCalendly($customer, (int) $userId, $report, $dryRun);
        $this->importGoogleCalendar($customer, (int) $userId, $report, $dryRun);
        $this->importWebsiteTrackers($customer, $ids, $report, $dryRun);
        $this->importGoogleCalendarBookingLinks($customer, (int) $userId, $ids, $report, $dryRun);
    }

    private function importWebsiteTrackers(
        LegacyCustomerSnapshot $customer,
        MigrationIdMap $ids,
        MigrationReport $report,
        bool $dryRun,
    ): void {
        if (! $this->legacy->tableExists('websites')) {
            return;
        }

        $rows = $this->legacy->db()->table('websites')
            ->where('customer_id', $customer->id)
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $legacyId = (int) $row->id;
            $name = trim((string) ($row->title ?? $row->name ?? 'Website '.$legacyId));
            $domain = $this->extractDomain((string) ($row->url ?? $row->domain ?? ''));
            if ($domain === null) {
                $report->bump('website_trackers', 'skipped');

                continue;
            }

            $status = strtolower((string) ($row->status ?? 'inactive'));
            $status = in_array($status, ['connected', 'active', '1', 'enabled'], true) ? 'active' : 'inactive';

            $existingId = $ids->getInt('website_tracker', $legacyId);
            $existing = $existingId
                ? \App\Domains\ThirdParty\Models\WebsiteTracker::query()->find($existingId)
                : \App\Domains\ThirdParty\Models\WebsiteTracker::query()->where('domain', $domain)->first();

            if ($dryRun) {
                $report->bump('website_trackers', $existing ? 'updated' : 'created');

                continue;
            }

            $attributes = [
                'name' => $name !== '' ? $name : $domain,
                'domain' => $domain,
                'status' => $status,
            ];

            if ($existing !== null) {
                $existing->forceFill($attributes)->save();
                $tracker = $existing;
                $report->bump('website_trackers', 'updated');
            } else {
                $tracker = \App\Domains\ThirdParty\Models\WebsiteTracker::query()->create($attributes);
                $report->bump('website_trackers', 'created');
            }

            $ids->put('website_tracker', $legacyId, $tracker->id);
        }
    }

    private function importGoogleCalendarBookingLinks(
        LegacyCustomerSnapshot $customer,
        int $userId,
        MigrationIdMap $ids,
        MigrationReport $report,
        bool $dryRun,
    ): void {
        if (! $this->legacy->tableExists('google_calendar_booking_links')) {
            return;
        }

        $legacyUserIds = $this->legacy->db()->table('users')
            ->where('customer_id', $customer->id)
            ->pluck('id');

        if ($legacyUserIds->isEmpty()) {
            return;
        }

        $rows = $this->legacy->db()->table('google_calendar_booking_links')
            ->whereIn('user_id', $legacyUserIds)
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $legacyId = (int) $row->id;
            $slug = trim((string) ($row->slug ?? ''));
            if ($slug === '') {
                $report->bump('gcal_booking_links', 'skipped');

                continue;
            }

            $mappedUserId = $ids->getInt('legacy_user', (int) ($row->user_id ?? 0)) ?? $userId;
            $status = strtolower((string) ($row->status ?? 'enabled'));
            $enabled = in_array($status, ['enabled', 'active', '1'], true);

            $settings = $row->settings ?? null;
            if (is_string($settings) && $settings !== '') {
                $decoded = json_decode($settings, true);
                $settings = is_array($decoded) ? $decoded : null;
            }

            $existingId = $ids->getInt('gcal_booking_link', $legacyId);
            $existing = $existingId
                ? \App\Domains\ThirdParty\Models\GoogleCalendarBookingLink::query()->find($existingId)
                : \App\Domains\ThirdParty\Models\GoogleCalendarBookingLink::query()->where('slug', $slug)->first();

            if ($dryRun) {
                $report->bump('gcal_booking_links', $existing ? 'updated' : 'created');

                continue;
            }

            $attributes = [
                'user_id' => $mappedUserId,
                'slug' => $slug,
                'title' => trim((string) ($row->title ?? $slug)),
                'duration_minutes' => (int) ($row->duration_minutes ?? 30),
                'status' => $enabled
                    ? \App\Domains\ThirdParty\Enums\IntegrationStatus::Enabled
                    : \App\Domains\ThirdParty\Enums\IntegrationStatus::Disabled,
                'settings' => is_array($settings) ? $settings : [],
            ];

            if ($existing !== null) {
                $existing->forceFill($attributes)->save();
                $link = $existing;
                $report->bump('gcal_booking_links', 'updated');
            } else {
                $link = \App\Domains\ThirdParty\Models\GoogleCalendarBookingLink::query()->create($attributes);
                $report->bump('gcal_booking_links', 'created');
            }

            $ids->put('gcal_booking_link', $legacyId, $link->id);
        }
    }

    private function extractDomain(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (! str_contains($url, '://')) {
            $url = 'https://'.$url;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower($host) : null;
    }

    private function importShopify(
        LegacyCustomerSnapshot $customer,
        int $userId,
        MigrationReport $report,
        bool $dryRun,
    ): void {
        $table = $this->legacy->tableExists('shopifydetails')
            ? 'shopifydetails'
            : ($this->legacy->tableExists('shopify_details') ? 'shopify_details' : null);

        if ($table === null) {
            return;
        }

        $query = $this->legacy->db()->table($table);
        if ($this->legacy->hasColumn($table, 'customer_id')) {
            $query->where('customer_id', $customer->id);
        }

        foreach ($query->orderBy('id')->get() as $row) {
            $settings = $this->rowToSettings($row, [
                'shopifydomainurl' => ['shopifydomainurl', 'domain', 'shop_domain', 'store_url'],
                'api_key' => ['api_key', 'apikey'],
                'api_secret' => ['api_secret', 'apisecret', 'secret'],
                'access_token' => ['access_token', 'token'],
                'legacy_id' => ['id'],
            ]);

            if ($dryRun) {
                $report->bump('shopify', ShopifyIntegration::query()->where('user_id', $userId)->exists() ? 'updated' : 'created');

                continue;
            }

            $existing = ShopifyIntegration::query()->where('user_id', $userId)->first();
            $attributes = [
                'user_id' => $userId,
                'status' => $this->mapEnabled($row) ? IntegrationStatus::Enabled : IntegrationStatus::Disabled,
                'settings' => $settings,
            ];

            if ($existing !== null) {
                $existing->forceFill($attributes)->save();
                $report->bump('shopify', 'updated');
            } else {
                ShopifyIntegration::query()->create($attributes);
                $report->bump('shopify', 'created');
            }
        }
    }

    private function importCalendly(
        LegacyCustomerSnapshot $customer,
        int $userId,
        MigrationReport $report,
        bool $dryRun,
    ): void {
        if (! $this->legacy->tableExists('calendly_integrations')) {
            return;
        }

        $query = $this->legacy->db()->table('calendly_integrations');
        if ($this->legacy->hasColumn('calendly_integrations', 'customer_id')) {
            $query->where('customer_id', $customer->id);
        } elseif ($this->legacy->hasColumn('calendly_integrations', 'user_id')) {
            // Legacy may key by user; take all rows for this customer when no customer_id.
        }

        foreach ($query->orderBy('id')->get() as $row) {
            $settings = $this->rowToSettings($row, [
                'access_token' => ['access_token', 'token'],
                'refresh_token' => ['refresh_token'],
                'organization' => ['organization', 'org_uri'],
                'user_uri' => ['user_uri', 'calendly_user'],
                'webhook_signing_key' => ['webhook_signing_key', 'signing_key'],
                'legacy_id' => ['id'],
            ]);

            if (isset($row->settings) && is_string($row->settings) && $row->settings !== '') {
                $decoded = json_decode($row->settings, true);
                if (is_array($decoded)) {
                    $settings = array_merge($settings, $decoded);
                }
            }

            if ($dryRun) {
                $report->bump('calendly', CalendlyIntegration::query()->where('user_id', $userId)->exists() ? 'updated' : 'created');

                continue;
            }

            $existing = CalendlyIntegration::query()->where('user_id', $userId)->first();
            $attributes = [
                'user_id' => $userId,
                'status' => $this->mapEnabled($row) ? IntegrationStatus::Enabled : IntegrationStatus::Disabled,
                'settings' => $settings,
                'first_synced_at' => $row->first_synced_at ?? null,
            ];

            if ($existing !== null) {
                $existing->forceFill($attributes)->save();
                $report->bump('calendly', 'updated');
            } else {
                CalendlyIntegration::query()->create($attributes);
                $report->bump('calendly', 'created');
            }
        }
    }

    private function importGoogleCalendar(
        LegacyCustomerSnapshot $customer,
        int $userId,
        MigrationReport $report,
        bool $dryRun,
    ): void {
        if (! $this->legacy->tableExists('google_calendar_integrations')) {
            return;
        }

        $query = $this->legacy->db()->table('google_calendar_integrations');
        if ($this->legacy->hasColumn('google_calendar_integrations', 'customer_id')) {
            $query->where('customer_id', $customer->id);
        }

        foreach ($query->orderBy('id')->get() as $row) {
            $settings = $this->rowToSettings($row, [
                'access_token' => ['access_token', 'token'],
                'refresh_token' => ['refresh_token'],
                'calendar_id' => ['calendar_id'],
                'email' => ['email', 'google_email'],
                'meet_only' => ['meet_only'],
                'reminder_minutes_before' => ['reminder_minutes_before', 'reminder_minutes'],
                'legacy_id' => ['id'],
            ]);

            if (isset($row->settings) && is_string($row->settings) && $row->settings !== '') {
                $decoded = json_decode($row->settings, true);
                if (is_array($decoded)) {
                    $settings = array_merge($settings, $decoded);
                }
            }

            if ($dryRun) {
                $report->bump('google_calendar', GoogleCalendarIntegration::query()->where('user_id', $userId)->exists() ? 'updated' : 'created');

                continue;
            }

            $existing = GoogleCalendarIntegration::query()->where('user_id', $userId)->first();
            $attributes = [
                'user_id' => $userId,
                'status' => $this->mapEnabled($row) ? IntegrationStatus::Enabled : IntegrationStatus::Disabled,
                'settings' => $settings,
                'first_synced_at' => $row->first_synced_at ?? null,
            ];

            if ($existing !== null) {
                $existing->forceFill($attributes)->save();
                $report->bump('google_calendar', 'updated');
            } else {
                GoogleCalendarIntegration::query()->create($attributes);
                $report->bump('google_calendar', 'created');
            }
        }
    }

    /**
     * @param  array<string, list<string>>  $map
     * @return array<string, mixed>
     */
    private function rowToSettings(object $row, array $map): array
    {
        $settings = [];
        foreach ($map as $target => $sources) {
            foreach ($sources as $source) {
                if (isset($row->{$source}) && $row->{$source} !== null && $row->{$source} !== '') {
                    $settings[$target] = $row->{$source};
                    break;
                }
            }
        }

        return $settings;
    }

    private function mapEnabled(object $row): bool
    {
        if (isset($row->status)) {
            $status = strtolower((string) $row->status);

            return in_array($status, ['1', 'enabled', 'active', 'connected', 'true'], true);
        }

        if (isset($row->enabled)) {
            return (bool) $row->enabled;
        }

        return true;
    }
}
