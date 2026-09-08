<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Services;

use App\Enums\CampaignRecipientStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Support\PhoneNormalizer;
use Illuminate\Http\UploadedFile;

class CampaignCsvImportService
{
    /**
     * @return array{imported: int, skipped: int}
     */
    public function import(Campaign $campaign, UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            abort(422, 'Unable to read CSV file.');
        }

        $header = fgetcsv($handle);
        $imported = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $data = $this->mapRow($header, $row);
            $phone = PhoneNormalizer::normalize((string) ($data['phone'] ?? '')) ?? trim((string) ($data['phone'] ?? ''));

            if ($phone === '') {
                $skipped++;

                continue;
            }

            $contact = Contact::query()->where('phone', $phone)->first();

            $exists = CampaignRecipient::query()
                ->where('campaign_id', $campaign->id)
                ->where('contact_phone', $phone)
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            CampaignRecipient::query()->create([
                'campaign_id' => $campaign->id,
                'contact_id' => $contact?->id,
                'contact_phone' => $phone,
                'variable_values' => $this->extractVariables($data),
                'status' => CampaignRecipientStatus::Pending,
            ]);

            $imported++;
        }

        fclose($handle);

        $campaign->update([
            'total_recipients' => CampaignRecipient::query()->where('campaign_id', $campaign->id)->count(),
        ]);

        return compact('imported', 'skipped');
    }

    /**
     * @param  array<int, string|null>|false  $header
     * @param  array<int, string|null>  $row
     * @return array<string, string>
     */
    private function mapRow(array|false $header, array $row): array
    {
        if ($header === false) {
            return ['phone' => (string) ($row[0] ?? '')];
        }

        $mapped = [];

        foreach ($header as $index => $column) {
            $key = strtolower(trim((string) $column));
            $mapped[$key] = (string) ($row[$index] ?? '');
        }

        return $mapped;
    }

    /**
     * @param  array<string, string>  $data
     * @return array<string, string>
     */
    private function extractVariables(array $data): array
    {
        unset($data['phone'], $data['name'], $data['email']);

        return array_filter($data, fn (string $value) => $value !== '');
    }
}
