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
        $credentials = $this->legacyWabaCredentials($customer->id);

        if ($credentials['waba_id'] === '' && $credentials['cust_space_id'] === '') {
            $report->warn("No WABA ID / customer space found in legacy business_infos for customer #{$customer->id}.");
        }

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

            $rowWabaId = trim((string) data_get($row, 'waba_id', ''));
            $rowCustSpaceId = trim((string) (
                data_get($row, 'cust_space_id')
                ?: data_get($row, 'alibaba_cust_space_id')
                ?: ''
            ));
            if ($rowWabaId !== '') {
                $attributes['waba_id'] = $rowWabaId;
            } elseif ($credentials['waba_id'] !== '') {
                $attributes['waba_id'] = $credentials['waba_id'];
            }
            if ($rowCustSpaceId !== '') {
                $attributes['alibaba_cust_space_id'] = $rowCustSpaceId;
            } elseif ($credentials['cust_space_id'] !== '') {
                $attributes['alibaba_cust_space_id'] = $credentials['cust_space_id'];
            }

            foreach (['business_name', 'business_id', 'business_verification_status', 'vertical'] as $metaKey) {
                if ($credentials[$metaKey] !== '') {
                    $attributes['metadata'][$metaKey] = $credentials[$metaKey];
                }
            }

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
     * @return array{
     *     waba_id: string,
     *     cust_space_id: string,
     *     business_name: string,
     *     business_id: string,
     *     business_verification_status: string,
     *     vertical: string
     * }
     */
    private function legacyWabaCredentials(int $customerId): array
    {
        $found = [
            'waba_id' => '',
            'cust_space_id' => '',
            'business_name' => '',
            'business_id' => '',
            'business_verification_status' => '',
            'vertical' => '',
        ];

        if (! $this->legacy->tableExists('business_infos')) {
            return $found;
        }

        $hasCustomerId = $this->legacy->hasColumn('business_infos', 'customer_id');
        $hasUserId = $this->legacy->hasColumn('business_infos', 'user_id');
        if (! $hasCustomerId && ! $hasUserId) {
            return $found;
        }

        $query = $this->legacy->db()->table('business_infos');
        $query->where(function ($nested) use ($customerId, $hasCustomerId, $hasUserId): void {
            if ($hasCustomerId) {
                $nested->where('customer_id', $customerId);
            }

            if ($hasUserId && $this->legacy->tableExists('users')) {
                $userIds = $this->legacy->db()->table('users')->where('customer_id', $customerId)->pluck('id');
                if ($userIds->isNotEmpty()) {
                    $nested->orWhereIn('user_id', $userIds->all());
                }
            }
        });

        if ($this->legacy->hasColumn('business_infos', 'id')) {
            $query->orderByDesc('id');
        }

        foreach ($query->get() as $business) {
            $this->mergeCredential($found, 'waba_id', data_get($business, 'waba_id'));
            $this->mergeCredential($found, 'cust_space_id', data_get($business, 'cust_space_id') ?: data_get($business, 'alibaba_cust_space_id'));
            $this->mergeCredential($found, 'business_name', data_get($business, 'business_name'));
            $this->mergeCredential($found, 'business_id', data_get($business, 'business_id'));
            $this->mergeCredential($found, 'business_verification_status', data_get($business, 'status'));
            $this->mergeCredential($found, 'vertical', data_get($business, 'vertical'));

            $fromWabaResponse = $this->idsFromJsonBlob(data_get($business, 'waba_response'));
            $this->mergeCredential($found, 'waba_id', $fromWabaResponse['waba_id']);
            $this->mergeCredential($found, 'cust_space_id', $fromWabaResponse['cust_space_id']);

            $fromCustResponse = $this->idsFromJsonBlob(data_get($business, 'cust_response'));
            $this->mergeCredential($found, 'waba_id', $fromCustResponse['waba_id']);
            $this->mergeCredential($found, 'cust_space_id', $fromCustResponse['cust_space_id']);
        }

        if ($this->legacy->tableExists('customers')) {
            $customer = $this->legacy->db()->table('customers')->where('id', $customerId)->first();
            if ($customer !== null) {
                $this->mergeCredential($found, 'waba_id', data_get($customer, 'waba_id'));
                $this->mergeCredential($found, 'cust_space_id', data_get($customer, 'cust_space_id'));
            }
        }

        return $found;
    }

    /**
     * @param  array<string, string>  $found
     */
    private function mergeCredential(array &$found, string $key, mixed $value): void
    {
        if ($found[$key] !== '') {
            return;
        }

        $id = trim((string) ($value ?? ''));
        if ($id !== '' && ! in_array(strtolower($id), ['null', '0', 'false'], true)) {
            $found[$key] = $id;
        }
    }

    /**
     * @return array{waba_id: string, cust_space_id: string}
     */
    private function idsFromJsonBlob(mixed $raw): array
    {
        $ids = ['waba_id' => '', 'cust_space_id' => ''];
        if (! is_string($raw) || $raw === '' || ! str_starts_with(ltrim($raw), '{')) {
            return $ids;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return $ids;
        }

        foreach (['wabaId', 'WabaId', 'waba_id', 'data.wabaId', 'data.WabaId', 'Data.WabaId', 'body.data.wabaId'] as $path) {
            $value = trim((string) (data_get($decoded, $path) ?? ''));
            if ($value !== '') {
                $ids['waba_id'] = $value;
                break;
            }
        }

        foreach (['custSpaceId', 'CustSpaceId', 'cust_space_id', 'data.custSpaceId', 'data.CustSpaceId', 'Data.CustSpaceId', 'body.data.custSpaceId'] as $path) {
            $value = trim((string) (data_get($decoded, $path) ?? ''));
            if ($value !== '') {
                $ids['cust_space_id'] = $value;
                break;
            }
        }

        return $ids;
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
