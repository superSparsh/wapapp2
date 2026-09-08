<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Services;

use App\Domains\Templates\Services\TemplatePreviewService;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Template;
use App\Support\PhoneNormalizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CampaignVariableGridService
{
    public const PER_PAGE = 100;

    public function __construct(
        private readonly TemplatePreviewService $previewService,
        private readonly CampaignTemplateParamsResolver $paramsResolver,
    ) {}

    /**
     * Template variable names shown in the grid (excludes unsub).
     *
     * @return list<string>
     */
    public function variableNames(Template $template): array
    {
        $names = [];
        foreach ($this->previewService->variablesForTemplate($template) as $variable) {
            $name = (string) ($variable['name'] ?? '');
            if ($name === '' || in_array($name, CampaignTemplateParamsResolver::HIDDEN_GRID_VARS, true)) {
                continue;
            }
            $names[] = $name;
        }

        // Legacy: full_name first when present.
        usort($names, function (string $a, string $b): int {
            if ($a === 'full_name') {
                return -1;
            }
            if ($b === 'full_name') {
                return 1;
            }

            return 0;
        });

        return array_values(array_unique($names));
    }

    /**
     * @return list<string>
     */
    public function customVariableNames(Template $template): array
    {
        return array_values(array_filter(
            $this->variableNames($template),
            fn (string $name): bool => ! $this->paramsResolver->isContactNameVar($name) && $name !== 'phone',
        ));
    }

    /**
     * @return array{
     *   variable_names: list<string>,
     *   custom_variable_names: list<string>,
     *   has_custom_vars: bool,
     *   rows: list<array{recipient_id: int, phone: string, values: array<string, string>, sources: array<string, string>}>,
     *   paginator: LengthAwarePaginator
     * }
     */
    public function page(Campaign $campaign, Template $template, int $page = 1): array
    {
        $variableNames = $this->variableNames($template);
        $customNames = $this->customVariableNames($template);
        $listFieldIndex = $this->paramsResolver->listFieldIndex($campaign->audience_id);

        $paginator = CampaignRecipient::query()
            ->with(['contact:id,name,phone,custom_fields'])
            ->where('campaign_id', $campaign->id)
            ->orderBy('id')
            ->paginate(self::PER_PAGE, ['*'], 'page', max(1, $page));

        $rows = [];
        foreach ($paginator->items() as $recipient) {
            /** @var CampaignRecipient $recipient */
            $auto = $this->paramsResolver->autoFill(
                $recipient->contact,
                (string) $recipient->contact_phone,
                $variableNames,
                $listFieldIndex,
            );

            $saved = is_array($recipient->variable_values) ? $recipient->variable_values : [];
            $values = $auto['values'];
            $sources = $auto['sources'];

            foreach ($variableNames as $name) {
                if (! array_key_exists($name, $saved)) {
                    continue;
                }
                if (! is_scalar($saved[$name]) && $saved[$name] !== null) {
                    continue;
                }
                $values[$name] = (string) $saved[$name];
                $sources[$name] = 'saved';
            }

            $rows[] = [
                'recipient_id' => (int) $recipient->id,
                'phone' => (string) $recipient->contact_phone,
                'values' => $values,
                'sources' => $sources,
            ];
        }

        return [
            'variable_names' => $variableNames,
            'custom_variable_names' => $customNames,
            'has_custom_vars' => $customNames !== [],
            'rows' => $rows,
            'paginator' => $paginator,
        ];
    }

    /**
     * @param  list<array{recipient_id: int, values: array<string, mixed>}>  $rows
     */
    public function save(Campaign $campaign, array $rows): int
    {
        $updated = 0;

        DB::transaction(function () use ($campaign, $rows, &$updated): void {
            foreach ($rows as $row) {
                $recipientId = (int) ($row['recipient_id'] ?? 0);
                if ($recipientId <= 0) {
                    continue;
                }

                $recipient = CampaignRecipient::query()
                    ->where('campaign_id', $campaign->id)
                    ->whereKey($recipientId)
                    ->first();

                if (! $recipient instanceof CampaignRecipient) {
                    continue;
                }

                $incoming = is_array($row['values'] ?? null) ? $row['values'] : [];
                $merged = is_array($recipient->variable_values) ? $recipient->variable_values : [];

                foreach ($incoming as $name => $value) {
                    if (! is_string($name) || $name === '') {
                        continue;
                    }
                    if (in_array($name, CampaignTemplateParamsResolver::HIDDEN_GRID_VARS, true)) {
                        continue;
                    }
                    if (in_array($name, CampaignTemplateParamsResolver::META_KEYS, true)) {
                        continue;
                    }
                    if (! is_scalar($value) && $value !== null) {
                        continue;
                    }
                    $stringValue = trim((string) ($value ?? ''));
                    if ($stringValue === '') {
                        unset($merged[$name]);
                    } else {
                        $merged[$name] = mb_substr($stringValue, 0, 60);
                    }
                }

                $recipient->update(['variable_values' => $merged === [] ? null : $merged]);
                $updated++;
            }
        });

        return $updated;
    }

    /**
     * Parse + apply CSV onto existing recipients (does not add new recipients).
     *
     * @return array{updated: int, skipped: int, missing: int, error?: string}
     */
    public function applyCsv(Campaign $campaign, Template $template, UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            return ['updated' => 0, 'skipped' => 0, 'missing' => 0, 'error' => 'Unable to read CSV file.'];
        }

        $header = fgetcsv($handle);
        if ($header === false || $header === [null] || $header === []) {
            fclose($handle);

            return ['updated' => 0, 'skipped' => 0, 'missing' => 0, 'error' => 'CSV file is empty or missing a header row.'];
        }

        $headers = array_map(
            static fn ($column): string => strtolower(trim((string) $column)),
            $header,
        );

        foreach (['first_name', 'last_name', 'full_name'] as $forbidden) {
            if (in_array($forbidden, $headers, true)) {
                fclose($handle);

                return [
                    'updated' => 0,
                    'skipped' => 0,
                    'missing' => 0,
                    'error' => 'CSV headers must not contain first_name, last_name, or full_name.',
                ];
            }
        }

        $phoneIndex = $this->phoneColumnIndex($headers);
        if ($phoneIndex === null) {
            fclose($handle);

            return [
                'updated' => 0,
                'skipped' => 0,
                'missing' => 0,
                'error' => 'CSV must include a whatsapp_number or phone column.',
            ];
        }

        $customNames = $this->customVariableNames($template);
        $customLookup = [];
        foreach ($customNames as $name) {
            $customLookup[strtolower($name)] = $name;
        }

        $columnMap = [];
        foreach ($headers as $index => $headerName) {
            if ($index === $phoneIndex) {
                continue;
            }
            if (isset($customLookup[$headerName])) {
                $columnMap[$index] = $customLookup[$headerName];
            }
        }

        $updated = 0;
        $skipped = 0;
        $missing = 0;
        $rowsToSave = [];

        while (($data = fgetcsv($handle)) !== false) {
            if ($this->isEmptyCsvRow($data)) {
                continue;
            }

            $rawPhone = (string) ($data[$phoneIndex] ?? '');
            $phone = PhoneNormalizer::normalize($rawPhone) ?? preg_replace('/\D+/', '', trim($rawPhone)) ?? '';
            if ($phone === '') {
                $skipped++;

                continue;
            }

            $recipient = $this->findRecipientByPhone($campaign->id, $phone);
            if (! $recipient instanceof CampaignRecipient) {
                $missing++;

                continue;
            }

            $values = [];
            foreach ($columnMap as $colIndex => $varName) {
                $values[$varName] = trim((string) ($data[$colIndex] ?? ''));
            }

            $rowsToSave[] = [
                'recipient_id' => (int) $recipient->id,
                'values' => $values,
            ];
        }

        fclose($handle);

        if ($rowsToSave !== []) {
            $updated = $this->save($campaign, $rowsToSave);
        }

        return compact('updated', 'skipped', 'missing');
    }

    /**
     * @param  list<string>  $headers
     */
    private function phoneColumnIndex(array $headers): ?int
    {
        foreach (['whatsapp_number', 'phone', 'phone_number', 'whatsapp'] as $candidate) {
            $index = array_search($candidate, $headers, true);
            if ($index !== false) {
                return (int) $index;
            }
        }

        return null;
    }

    private function findRecipientByPhone(int $campaignId, string $phone): ?CampaignRecipient
    {
        $variants = PhoneNormalizer::lookupVariants($phone);
        if ($variants === []) {
            $variants = [$phone];
        }

        return CampaignRecipient::query()
            ->where('campaign_id', $campaignId)
            ->whereIn('contact_phone', $variants)
            ->first();
    }

    /**
     * @param  array<int, string|null>  $data
     */
    private function isEmptyCsvRow(array $data): bool
    {
        foreach ($data as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
