<?php

declare(strict_types=1);

namespace App\Domains\Audience\Services;

use App\Domains\Audience\Enums\ContactStatus;
use App\Enums\ContactOptInStatus;
use App\Models\Contact;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;

class ContactImportService
{
    /**
     * Import contacts from a CSV file.
     *
     * @return array{imported: int, skipped: int, total: int}
     */
    public function import(UploadedFile $file, ?int $mailListId = null): array
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
        $headers = array_map(fn ($h) => strtolower(trim($h)), $headers);

        $imported = 0;
        $skipped = 0;
        $total = 0;
        $batch = [];
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

            $batch[] = [
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'phone' => $phone,
                'name' => $data['name'] ?? $data['first_name'] ?? null,
                'email' => $data['email'] ?? null,
                'country_code' => $data['country_code'] ?? null,
                'mail_list_id' => $mailListId,
                'status' => ContactStatus::Subscribed->value,
                'opt_in_status' => ContactOptInStatus::OptedIn->value,
                'opted_in_at' => now(),
                'source' => $data['source'] ?? 'import',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($batch) >= $batchSize) {
                $imported += $this->upsertBatch($batch);
                $batch = [];
            }
        }

        // Process remaining batch
        if (count($batch) > 0) {
            $imported += $this->upsertBatch($batch);
        }

        fclose($handle);

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
            ['name', 'email', 'country_code', 'mail_list_id', 'status', 'opt_in_status', 'source', 'updated_at'] // update
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
