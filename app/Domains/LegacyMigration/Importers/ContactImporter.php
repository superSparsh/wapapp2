<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Importers;

use App\Domains\LegacyMigration\DTO\LegacyCustomerSnapshot;
use App\Domains\LegacyMigration\Support\LegacyConnection;
use App\Domains\LegacyMigration\Support\MigrationIdMap;
use App\Domains\LegacyMigration\Support\MigrationReport;
use App\Enums\ContactOptInStatus;
use App\Models\Contact;
use App\Models\Tenant;
use App\Support\PhoneNormalizer;

final class ContactImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyConnection $legacy,
    ) {}

    public function key(): string
    {
        return 'contacts';
    }

    public function import(
        LegacyCustomerSnapshot $customer,
        Tenant $tenant,
        MigrationIdMap $ids,
        MigrationReport $report,
        bool $dryRun = false,
    ): void {
        if (! $this->legacy->tableExists('subscribers') || ! $this->legacy->tableExists('mail_lists')) {
            return;
        }

        $listIds = $this->legacy->db()->table('mail_lists')
            ->where('customer_id', $customer->id)
            ->pluck('id');

        if ($listIds->isEmpty()) {
            return;
        }

        $chunk = (int) config('legacy-migration.chunks.contacts', 500);

        $this->legacy->db()->table('subscribers')
            ->whereIn('mail_list_id', $listIds)
            ->orderBy('id')
            ->chunkById($chunk, function ($rows) use ($ids, $report, $dryRun): void {
                foreach ($rows as $row) {
                    $this->importOne($row, $ids, $report, $dryRun);
                }
            });
    }

    private function importOne(object $row, MigrationIdMap $ids, MigrationReport $report, bool $dryRun): void
    {
        $phone = $this->normalizeImportPhone((string) ($row->phone_number ?? ''));
        if ($phone === null) {
            $report->bump($this->key(), 'skipped');

            return;
        }

        $mailListId = $ids->getInt('list', (int) $row->mail_list_id);
        $name = trim(($row->first_name ?? '').' '.($row->last_name ?? ''));
        $legacyId = (int) $row->id;

        if ($dryRun) {
            $report->bump($this->key(), Contact::query()->where('phone', $phone)->exists() ? 'updated' : 'created');

            return;
        }

        $contact = Contact::query()->where('phone', $phone)->first();
        $payload = [
            'phone' => $phone,
            'name' => $name !== '' ? $name : null,
            'email' => filled($row->email ?? null) ? strtolower((string) $row->email) : null,
            'country_code' => $this->normalizeCountryCode($row->country_code ?? null, $phone),
            'opt_in_status' => $this->mapOptIn($row->status ?? null),
            'source' => 'legacy_import',
            'mail_list_id' => $mailListId ?? ($contact?->mail_list_id),
            'metadata' => array_merge($contact?->metadata ?? [], [
                'legacy_subscriber_id' => $legacyId,
                'legacy_uid' => $row->uid ?? null,
                'legacy_tags' => $row->tags ?? null,
                'legacy_country_code' => $row->country_code ?? null,
                'legacy_phone_raw' => $row->phone_number ?? null,
            ]),
        ];

        if ($contact !== null) {
            $contact->forceFill($payload)->save();
            $report->bump($this->key(), 'updated');
        } else {
            $contact = Contact::query()->create($payload);
            $report->bump($this->key(), 'created');
        }

        $ids->put('contact', $legacyId, $contact->id);
        $ids->put('contact_phone', $phone, $contact->id);
    }

    /**
     * Keep phones within DB limit (varchar 20) and E.164-ish length.
     */
    private function normalizeImportPhone(string $raw): ?string
    {
        $phone = PhoneNormalizer::normalize($raw);

        if ($phone === null) {
            return null;
        }

        // Column is string(20); E.164 max is 15 digits.
        if (strlen($phone) < 8 || strlen($phone) > 15) {
            return null;
        }

        // Reject obvious concatenated garbage (same digit repeated heavily).
        if (preg_match('/^(\d)\1{7,}$/', $phone) === 1) {
            return null;
        }

        return $phone;
    }

    private function normalizeCountryCode(mixed $raw, string $phone): ?string
    {
        $value = strtoupper(trim((string) $raw));

        if ($value === '') {
            return $this->guessCountryFromPhone($phone);
        }

        // Already ISO alpha-2 / alpha-3
        if (preg_match('/^[A-Z]{2,3}$/', $value) === 1) {
            return substr($value, 0, 3);
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        $dialMap = [
            '91' => 'IN',
            '971' => 'AE',
            '1' => 'US',
            '44' => 'GB',
            '61' => 'AU',
            '65' => 'SG',
            '60' => 'MY',
            '966' => 'SA',
            '974' => 'QA',
            '973' => 'BH',
            '968' => 'OM',
            '965' => 'KW',
        ];

        if ($digits !== '' && isset($dialMap[$digits])) {
            return $dialMap[$digits];
        }

        return $this->guessCountryFromPhone($phone);
    }

    private function guessCountryFromPhone(string $phone): ?string
    {
        if (str_starts_with($phone, '91') && strlen($phone) === 12) {
            return 'IN';
        }
        if (str_starts_with($phone, '971')) {
            return 'AE';
        }

        return null;
    }

    private function mapOptIn(mixed $status): ContactOptInStatus
    {
        $value = strtolower((string) $status);

        return match (true) {
            str_contains($value, 'unsub') => ContactOptInStatus::OptedOut,
            str_contains($value, 'sub') => ContactOptInStatus::OptedIn,
            str_contains($value, 'pending') => ContactOptInStatus::Pending,
            default => ContactOptInStatus::Unknown,
        };
    }
}
