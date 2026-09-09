<?php

declare(strict_types=1);

namespace App\Domains\Audience\Services;

use App\Domains\Audience\Enums\ContactStatus;
use App\Domains\Audience\Models\Blacklist;
use App\Enums\ContactOptInStatus;
use App\Models\Contact;
use Illuminate\Http\UploadedFile;

class ContactImportService
{
    /**
     * Import contacts from a CSV file.
     *
     * @return array{imported: int, skipped: int, total: int}
     */
    public function import(UploadedFile $file, ?int $mailListId = null, bool $sendOptIn = false): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if (! $handle) {
            throw new \RuntimeException('Unable to open CSV file.');
        }

        // Read header row
        $headers = fgetcsv($handle);
        if (! $headers) {
            fclose($handle);
            throw new \RuntimeException('CSV file is empty or has no header row.');
        }

        // Normalize headers
        $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $headers);

        $imported = 0;
        $skipped = 0;
        $total = 0;
        $batch = [];
        $importedPhones = [];
        $batchSize = 500;

        while (($row = fgetcsv($handle)) !== false) {
            $total++;
            $data = array_combine($headers, $row);
            if (! $data) {
                $skipped++;
                continue;
            }

            $phone = $this->extractPhone($data);
            if (! $phone) {
                $skipped++;
                continue;
            }

            $email = $data['email'] ?? null;
            if (Blacklist::isBlacklisted($phone, $email)) {
                $skipped++;
                continue;
            }

            $firstName = trim((string) ($data['first_name'] ?? $data['FIRST_NAME'] ?? ''));
            $lastName = trim((string) ($data['last_name'] ?? $data['LAST_NAME'] ?? ''));
            $fullName = trim($firstName.' '.$lastName);
            if ($fullName === '') {
                $fullName = $data['name'] ?? null;
            }

            $batch[] = [
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'phone' => $phone,
                'name' => $fullName,
                'email' => $email,
                'country_code' => $data['country_code'] ?? null,
                'mail_list_id' => $mailListId,
                'status' => ContactStatus::Subscribed->value,
                'opt_in_status' => ContactOptInStatus::OptedIn->value,
                'opted_in_at' => now(),
                'send_opt_in_message' => $sendOptIn ? 'yes' : 'no',
                'source' => $data['source'] ?? 'import',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $importedPhones[] = $phone;

            if (count($batch) >= $batchSize) {
                $imported += $this->upsertBatch($batch);
                $batch = [];
            }
        }

        if (count($batch) > 0) {
            $imported += $this->upsertBatch($batch);
        }

        fclose($handle);

        if ($sendOptIn && $importedPhones !== []) {
            $optIn = app(OptInMessageService::class);
            Contact::query()
                ->whereIn('phone', array_values(array_unique($importedPhones)))
                ->where('send_opt_in_message', 'yes')
                ->where(function ($q): void {
                    $q->where('opt_in_message_sent', false)->orWhereNull('opt_in_message_sent');
                })
                ->orderBy('id')
                ->chunkById(100, function ($contacts) use ($optIn): void {
                    foreach ($contacts as $contact) {
                        $optIn->sendOptInToContact($contact);
                    }
                });
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'total' => $total,
        ];
    }

    /**
     * Upsert a batch of contacts using phone as the unique key.
     */
    private function upsertBatch(array $batch): int
    {
        if (empty($batch)) {
            return 0;
        }

        Contact::upsert(
            $batch,
            ['phone'],           // unique by
            ['name', 'email', 'country_code', 'mail_list_id', 'status', 'opt_in_status', 'send_opt_in_message', 'source', 'updated_at'] // update
        );

        return count($batch);
    }

    /**
     * Extract phone number from CSV row data.
     */
    private function extractPhone(array $data): ?string
    {
        $phone = $data['phone'] ?? $data['phone_number'] ?? $data['whatsapp_number'] ?? null;

        return $phone ? trim($phone) : null;
    }
}
