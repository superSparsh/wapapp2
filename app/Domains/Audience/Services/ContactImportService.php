<?php

declare(strict_types=1);

namespace App\Domains\Audience\Services;

use App\Domains\Audience\Enums\ContactStatus;
use App\Domains\Audience\Models\Blacklist;
use App\Enums\ContactOptInStatus;
use App\Models\Contact;
use App\Support\PhoneNormalizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ContactImportService
{
    /** @var list<string> */
    public const REQUIRED_HEADERS = [
        'country_code',
        'phone_number',
        'first_name',
        'last_name',
        'existing_customer',
        'send_opt_in_message',
    ];

    /**
     * Import contacts from an uploaded CSV (sync helper / small files / tests).
     *
     * @return array{imported: int, skipped: int, total: int}
     */
    public function import(UploadedFile $file, ?int $mailListId = null, bool $forceSendOptIn = false): array
    {
        $path = $file->getRealPath();
        if ($path === false || $path === '') {
            throw new \RuntimeException('Unable to open CSV file.');
        }

        return $this->importFromPath($path, $mailListId, $forceSendOptIn);
    }

    /**
     * Import contacts from an absolute CSV path (used by queued job).
     *
     * @return array{imported: int, skipped: int, total: int}
     */
    public function importFromPath(string $absolutePath, ?int $mailListId = null, bool $forceSendOptIn = false): array
    {
        if ($mailListId === null || $mailListId <= 0) {
            throw new \RuntimeException('A target list is required for import.');
        }

        $handle = fopen($absolutePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open CSV file.');
        }

        try {
            $headers = fgetcsv($handle);
            if ($headers === false || $headers === [null] || $headers === []) {
                throw new \RuntimeException('CSV file is empty or has no header row.');
            }

            $headers = array_map(
                static fn ($h): string => strtolower(trim((string) $h)),
                $headers,
            );

            $this->assertRequiredHeaders($headers);

            $imported = 0;
            $skipped = 0;
            $total = 0;
            $batch = [];
            $importedPhones = [];
            $batchSize = 250;

            while (($row = fgetcsv($handle)) !== false) {
                if ($this->rowIsEmpty($row)) {
                    continue;
                }

                $total++;

                if (count($row) < count($headers)) {
                    $row = array_pad($row, count($headers), '');
                } elseif (count($row) > count($headers)) {
                    $row = array_slice($row, 0, count($headers));
                }

                $data = array_combine($headers, $row);
                if ($data === false) {
                    $skipped++;
                    continue;
                }

                $phone = $this->extractPhone($data);
                if ($phone === null) {
                    $skipped++;
                    continue;
                }

                $email = isset($data['email']) ? trim((string) $data['email']) : null;
                $email = $email !== '' ? $email : null;

                if (Blacklist::isBlacklisted($phone, $email)) {
                    $skipped++;
                    continue;
                }

                $firstName = trim((string) ($data['first_name'] ?? ''));
                $lastName = trim((string) ($data['last_name'] ?? ''));
                $fullName = trim($firstName.' '.$lastName);
                if ($fullName === '') {
                    $fullName = trim((string) ($data['name'] ?? '')) ?: null;
                }

                $existingCustomer = $this->normalizeYesNo($data['existing_customer'] ?? null, default: 'yes');
                $sendOptIn = $forceSendOptIn
                    ? 'yes'
                    : $this->normalizeYesNo($data['send_opt_in_message'] ?? null, default: 'no');

                $countryCode = trim((string) ($data['country_code'] ?? ''));
                $countryCode = $countryCode !== '' ? $countryCode : null;

                $now = now();
                $batch[] = [
                    'uuid' => (string) Str::uuid(),
                    'phone' => $phone,
                    'name' => $fullName,
                    'email' => $email,
                    'country_code' => $countryCode,
                    'mail_list_id' => $mailListId,
                    'status' => ContactStatus::Subscribed->value,
                    'opt_in_status' => ContactOptInStatus::OptedIn->value,
                    'opted_in_at' => $now,
                    'send_opt_in_message' => $sendOptIn,
                    'custom_fields' => json_encode(['existing_customer' => $existingCustomer], JSON_THROW_ON_ERROR),
                    'source' => trim((string) ($data['source'] ?? 'import')) ?: 'import',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if ($sendOptIn === 'yes') {
                    $importedPhones[] = $phone;
                }

                if (count($batch) >= $batchSize) {
                    $imported += $this->upsertBatch($batch);
                    $batch = [];
                }
            }

            if ($batch !== []) {
                $imported += $this->upsertBatch($batch);
            }
        } finally {
            fclose($handle);
        }

        $this->dispatchOptIn($mailListId, $importedPhones);

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'total' => $total,
        ];
    }

    /**
     * @param  list<string>  $headers
     */
    private function assertRequiredHeaders(array $headers): void
    {
        // Accept legacy aliases for the phone column.
        $normalized = $headers;
        if (in_array('whatsapp_number', $normalized, true) || in_array('phone', $normalized, true)) {
            $normalized = array_map(
                static fn (string $h): string => in_array($h, ['whatsapp_number', 'phone'], true) ? 'phone_number' : $h,
                $normalized,
            );
        }

        $missing = array_values(array_diff(self::REQUIRED_HEADERS, $normalized));
        if ($missing !== []) {
            throw new \RuntimeException(
                'Import missing required header field(s): '.implode(', ', $missing)
                .'. Download Sample.csv and keep the exact header row.',
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $batch
     */
    private function upsertBatch(array $batch): int
    {
        if ($batch === []) {
            return 0;
        }

        // Unique per (phone, mail_list_id) — same number can live on another list.
        Contact::upsert(
            $batch,
            ['phone', 'mail_list_id'],
            [
                'name',
                'email',
                'country_code',
                'status',
                'opt_in_status',
                'send_opt_in_message',
                'custom_fields',
                'source',
                'updated_at',
            ],
        );

        return count($batch);
    }

    /**
     * @param  list<string>  $importedPhones
     */
    private function dispatchOptIn(int $mailListId, array $importedPhones): void
    {
        $phones = array_values(array_unique(array_filter($importedPhones)));
        if ($phones === []) {
            return;
        }

        $optIn = app(OptInMessageService::class);

        Contact::query()
            ->where('mail_list_id', $mailListId)
            ->whereIn('phone', $phones)
            ->where('send_opt_in_message', 'yes')
            ->where(function ($q): void {
                $q->where('opt_in_message_sent', false)->orWhereNull('opt_in_message_sent');
            })
            ->orderBy('id')
            ->chunkById(50, function ($contacts) use ($optIn): void {
                foreach ($contacts as $contact) {
                    try {
                        $optIn->sendOptInToContact($contact);
                    } catch (\Throwable) {
                        // Per-contact failures are recorded on the contact; keep importing.
                    }
                }
            });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extractPhone(array $data): ?string
    {
        $raw = $data['phone_number'] ?? $data['phone'] ?? $data['whatsapp_number'] ?? null;
        $raw = is_string($raw) || is_numeric($raw) ? trim((string) $raw) : '';
        if ($raw === '') {
            return null;
        }

        $country = trim((string) ($data['country_code'] ?? ''));
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        $countryDigits = preg_replace('/\D+/', '', $country) ?? '';

        if ($digits !== '' && $countryDigits !== '' && ! str_starts_with($digits, $countryDigits)) {
            $digits = $countryDigits.$digits;
        }

        return PhoneNormalizer::normalize($digits);
    }

    private function normalizeYesNo(mixed $value, string $default): string
    {
        if ($value === null) {
            return $default;
        }

        $raw = strtolower(trim((string) $value));
        if ($raw === '') {
            return $default;
        }

        return in_array($raw, ['yes', 'y', 'true', '1'], true) ? 'yes' : 'no';
    }

    /**
     * @param  list<string|null>|false  $row
     */
    private function rowIsEmpty(array|false $row): bool
    {
        if ($row === false || $row === []) {
            return true;
        }

        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
