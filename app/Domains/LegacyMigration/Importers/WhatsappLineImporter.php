<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Importers;

use App\Domains\LegacyMigration\DTO\LegacyCustomerSnapshot;
use App\Domains\LegacyMigration\Support\LegacyConnection;
use App\Domains\LegacyMigration\Support\MigrationIdMap;
use App\Domains\LegacyMigration\Support\MigrationReport;
use App\Domains\Webhooks\Services\WhatsappLineRegistryService;
use App\Enums\RecordStatus;
use App\Models\Tenant;
use App\Models\WhatsappLine;
use App\Support\PhoneNormalizer;

final class WhatsappLineImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyConnection $legacy,
        private readonly WhatsappLineRegistryService $registry,
    ) {}

    public function key(): string
    {
        return 'lines';
    }

    public function import(
        LegacyCustomerSnapshot $customer,
        Tenant $tenant,
        MigrationIdMap $ids,
        MigrationReport $report,
        bool $dryRun = false,
    ): void {
        if (! $this->legacy->tableExists('new_contacts')) {
            return;
        }

        $rows = $this->legacy->db()->table('new_contacts')
            ->where('customer_id', $customer->id)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        $defaultAssigned = WhatsappLine::query()->where('is_default', true)->exists();

        foreach ($rows as $row) {
            $phone = PhoneNormalizer::normalize((string) $row->phone);
            if ($phone === null) {
                $report->warn("Skipped line #{$row->id}: invalid phone.");
                $report->bump($this->key(), 'skipped');

                continue;
            }

            if ($dryRun) {
                $report->bump($this->key(), WhatsappLine::query()->where('phone', $phone)->exists() ? 'updated' : 'created');

                continue;
            }

            $isDefault = ((int) ($row->is_default ?? 0) === 1) || ! $defaultAssigned;
            if ($isDefault) {
                WhatsappLine::query()->where('is_default', true)->update(['is_default' => false]);
                $defaultAssigned = true;
            }

            $attributes = [
                'display_name' => $row->verified_name ?: $phone,
                'status' => $this->mapStatus($row->status ?? null),
                'is_default' => $isDefault,
                'quality_rating' => $row->quality_rating ?? null,
                'messaging_limit_tier' => $row->message_limiter ?? null,
                'metadata' => [
                    'legacy_id' => (int) $row->id,
                    'legacy_verification_status' => $row->verification_status ?? null,
                ],
            ];

            $profile = $this->legacyProfile((int) $row->id, $customer->id);
            if ($profile !== null) {
                $attributes['profile'] = $profile;
            }

            $line = WhatsappLine::query()->updateOrCreate(
                ['phone' => $phone],
                $attributes,
            );

            $ids->put('line', (int) $row->id, $line->id);
            $this->registry->syncLine($tenant->id, (int) $line->id, $phone);
            $report->bump($this->key(), $line->wasRecentlyCreated ? 'created' : 'updated');
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function legacyProfile(int $legacyLineId, int $customerId): ?array
    {
        if (! $this->legacy->tableExists('phone_number_profiles')) {
            return null;
        }

        $query = $this->legacy->db()->table('phone_number_profiles');
        if ($this->legacy->hasColumn('phone_number_profiles', 'new_contact_id')) {
            $query->where('new_contact_id', $legacyLineId);
        } elseif ($this->legacy->hasColumn('phone_number_profiles', 'contact_id')) {
            $query->where('contact_id', $legacyLineId);
        } else {
            return null;
        }

        if ($this->legacy->hasColumn('phone_number_profiles', 'customer_id')) {
            $query->where('customer_id', $customerId);
        }

        $profile = $query->orderByDesc('id')->first();
        if ($profile === null) {
            return null;
        }

        return array_filter([
            'email' => $profile->email ?? $profile->business_email ?? null,
            'website' => $profile->website ?? $profile->websites ?? null,
            'address' => $profile->address ?? null,
            'description' => $profile->description ?? $profile->about ?? null,
            'about' => $profile->about ?? null,
            'logo_path' => $profile->logo ?? $profile->profile_picture_url ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function mapStatus(mixed $status): RecordStatus
    {
        $value = strtolower((string) $status);

        return match (true) {
            in_array($value, ['0', 'inactive', 'disabled', 'banned'], true) => RecordStatus::Inactive,
            default => RecordStatus::Active,
        };
    }
}
