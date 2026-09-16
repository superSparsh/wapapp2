<?php

declare(strict_types=1);

namespace App\Domains\Templates\Services;

use App\Domains\Templates\Enums\TemplateSource;
use App\Domains\Templates\Enums\TemplateStatus;
use App\Domains\Templates\Support\CamsTemplateIdentity;
use App\Domains\Templates\Support\TemplateCategoryCatalog;
use App\Domains\WhatsApp\Services\AlibabaCamsClient;
use App\Models\Template;
use App\Models\TemplateStatusLog;
use App\Models\WhatsappLine;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class TemplateSyncService
{
    public function __construct(
        private readonly AlibabaCamsClient $camsClient,
    ) {}

    /**
     * Sync the status of the first pending template (no provider code yet).
     * Legacy: getTemplates:byName → ListChatappTemplate by Name.
     */
    public function syncFirstPending(): void
    {
        $template = Template::query()
            ->where('status', TemplateStatus::PendingReview)
            ->where(function ($query): void {
                $query->whereNull('code')->orWhere('code', '');
            })
            ->orderBy('updated_at')
            ->first();

        if (! $template instanceof Template) {
            return;
        }

        $this->syncTemplateStatus($template);
    }

    /**
     * Batch-sync pending templates that already have a real CAMS TemplateCode.
     * Legacy: getDetails:templates → GetChatappTemplateDetail.
     *
     * @return array{processed: int, success: int, errors: int, category_updates: int}
     */
    public function syncBatch(int $limit = 50): array
    {
        $templates = Template::query()
            ->where('status', TemplateStatus::PendingReview)
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->orderBy('synced_at')
            ->limit(max($limit * 3, 50))
            ->get()
            ->filter(fn (Template $row): bool => CamsTemplateIdentity::isProviderCode($row->code))
            ->take($limit)
            ->values();

        return $this->syncTemplates($templates);
    }

    /**
     * Daily sync: GetChatappTemplateDetail for coded templates (status + Meta category drift).
     *
     * @return array{processed: int, success: int, errors: int, category_updates: int}
     */
    public function syncCodedDetailsBatch(int $limit = 50): array
    {
        $templates = Template::query()
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->orderBy('synced_at')
            ->orderBy('updated_at')
            ->limit(max($limit * 3, 50))
            ->get()
            ->filter(fn (Template $row): bool => CamsTemplateIdentity::isProviderCode($row->code))
            ->take($limit)
            ->values();

        return $this->syncTemplates($templates);
    }

    /**
     * Delete the first soft-deleted template that still has a WhatsApp code.
     */
    public function deleteFirstReady(): void
    {
        $template = Template::query()
            ->onlyTrashed()
            ->orderBy('deleted_at')
            ->get()
            ->first(fn (Template $row): bool => filled($row->whatsappCode()));

        if (! $template instanceof Template) {
            return;
        }

        $code = $template->whatsappCode();
        $line = $template->whatsappLine;
        if (! $line instanceof WhatsappLine || blank($line->alibaba_cust_space_id) || blank($code)) {
            $template->forceDelete();

            return;
        }

        try {
            $response = $this->camsClient->deleteChatappTemplate([
                'TemplateCode' => $code,
                'CustSpaceId' => $line->alibaba_cust_space_id,
            ]);

            if ($response->successful()) {
                $template->forceDelete();
            } else {
                Log::warning('WhatsApp template deletion failed', [
                    'template_id' => $template->id,
                    'response' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Template deletion error', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  iterable<int, Template>  $templates
     * @return array{processed: int, success: int, errors: int, category_updates: int}
     */
    private function syncTemplates(iterable $templates): array
    {
        $processed = 0;
        $success = 0;
        $errors = 0;
        $categoryUpdates = 0;

        foreach ($templates as $template) {
            try {
                $processed++;
                $changedCategory = $this->syncTemplateStatus($template);
                $success++;
                if ($changedCategory) {
                    $categoryUpdates++;
                }
            } catch (\Throwable $e) {
                $errors++;
                Log::warning('Template sync failed', [
                    'template_id' => $template->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'processed' => $processed,
            'success' => $success,
            'errors' => $errors,
            'category_updates' => $categoryUpdates,
        ];
    }

    /**
     * Sync a single template's status (and category) from WhatsApp.
     *
     * @return bool True when local category was updated from CAMS
     */
    private function syncTemplateStatus(Template $template): bool
    {
        $line = $template->whatsappLine;
        if (! $line instanceof WhatsappLine || blank($line->alibaba_cust_space_id)) {
            return false;
        }

        $categoryChanged = false;
        $providerCode = $template->whatsappCode();

        try {
            $params = ['CustSpaceId' => $line->alibaba_cust_space_id];
            $language = CamsTemplateIdentity::language($template->language);

            if ($providerCode) {
                $params['TemplateCode'] = $providerCode;
                $params['Language'] = $language;
                $response = $this->camsClient->getChatappTemplateDetail($params);
            } else {
                // Legacy ListChatappTemplate by Name when TemplateCode is not known yet.
                $params['Name'] = $this->normalizeName($template->name);
                $params['Language'] = $language;
                $response = $this->camsClient->listTemplates($params);
            }

            if (! $response->successful()) {
                Log::warning('Template status sync HTTP failure', [
                    'template_id' => $template->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            $body = $response->json() ?? [];
            if (! is_array($body)) {
                return false;
            }

            $fields = $this->extractAuditFields($body);
            $auditStatus = $fields['audit_status'];
            $reason = $fields['reason'];
            $templateCode = $fields['template_code'] ?: $providerCode;
            $remoteCategory = $fields['category'];

            if ($auditStatus === null && $templateCode === null) {
                Log::info('Template status sync: no CAMS match yet', [
                    'template_id' => $template->id,
                    'name' => $template->name,
                    'code' => $providerCode,
                ]);

                $template->forceFill(['synced_at' => now()])->save();

                return false;
            }

            $previousStatus = $template->status;
            $previousCategory = (string) ($template->category ?? '');
            $updateData = [
                'synced_at' => now(),
            ];

            if ($auditStatus !== null) {
                [$newStatus, $rejectionReason] = $this->mapAuditStatus($auditStatus, $reason);
                $updateData['status'] = $newStatus;

                if ($newStatus === TemplateStatus::Rejected) {
                    $updateData['rejection_reason'] = $rejectionReason
                        ? \Illuminate\Support\Str::limit($rejectionReason, 500)
                        : null;
                }

                if ($newStatus === TemplateStatus::Approved) {
                    $updateData['rejection_reason'] = null;
                    $updateData['source'] = TemplateSource::Cams;
                }
            }

            if (filled($templateCode) && CamsTemplateIdentity::isProviderCode((string) $templateCode)) {
                $updateData['code'] = (string) $templateCode;
                $updateData['source'] = TemplateSource::Cams;
            }

            $normalizedCategory = $this->normalizeRemoteCategory($remoteCategory, $previousCategory);

            if ($normalizedCategory !== null && strtoupper($previousCategory) !== $normalizedCategory) {
                $updateData['category'] = $normalizedCategory;
                $payload = $template->wizardPayload();
                $payload['meta']['category'] = $normalizedCategory;
                $updateData['payload'] = $payload;
                $categoryChanged = true;
            }

            $template->update($updateData);

            if ($categoryChanged) {
                Log::info('Template category updated from CAMS', [
                    'template_id' => $template->id,
                    'code' => $template->code,
                    'previous_category' => $previousCategory,
                    'new_category' => $normalizedCategory,
                ]);
            }

            if (isset($updateData['status']) && $previousStatus !== $updateData['status']) {
                TemplateStatusLog::query()->create([
                    'template_id' => $template->id,
                    'previous_status' => $previousStatus->value,
                    'new_status' => $updateData['status']->value,
                    'reason' => $updateData['rejection_reason'] ?? null,
                    'meta' => array_filter([
                        'audit_status' => $auditStatus,
                        'category' => $normalizedCategory,
                        'category_changed' => $categoryChanged ?: null,
                        'previous_category' => $categoryChanged ? $previousCategory : null,
                    ]),
                ]);
            } elseif ($categoryChanged) {
                TemplateStatusLog::query()->create([
                    'template_id' => $template->id,
                    'previous_status' => $previousStatus->value,
                    'new_status' => $previousStatus->value,
                    'reason' => "Category updated to {$normalizedCategory}",
                    'meta' => [
                        'category' => $normalizedCategory,
                        'category_changed' => true,
                        'previous_category' => $previousCategory,
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Template status sync failed', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $categoryChanged;
    }

    /**
     * Pull AuditStatus / TemplateCode / Category from GetChatappTemplateDetail or ListChatappTemplate bodies.
     *
     * @param  array<string, mixed>  $body
     * @return array{audit_status: ?string, reason: ?string, template_code: ?string, category: ?string}
     */
    private function extractAuditFields(array $body): array
    {
        $data = $this->extractDetailPayload($body);
        $listItem = $this->firstListTemplateItem($body, $data);

        $auditStatus = $data['auditStatus']
            ?? $data['AuditStatus']
            ?? $body['AuditStatus']
            ?? $body['auditStatus']
            ?? ($listItem['AuditStatus'] ?? $listItem['auditStatus'] ?? null);

        $reason = $data['reason']
            ?? $data['Reason']
            ?? $body['Reason']
            ?? $body['reason']
            ?? ($listItem['Reason'] ?? $listItem['reason'] ?? null);

        $templateCode = $data['templateCode']
            ?? $data['TemplateCode']
            ?? $body['TemplateCode']
            ?? $body['templateCode']
            ?? ($listItem['TemplateCode'] ?? $listItem['templateCode'] ?? null);

        $category = $data['category']
            ?? $data['Category']
            ?? $body['Category']
            ?? $body['category']
            ?? ($listItem['Category'] ?? $listItem['category'] ?? null);

        return [
            'audit_status' => is_string($auditStatus) && $auditStatus !== '' ? $auditStatus : null,
            'reason' => is_string($reason) && $reason !== '' ? $reason : null,
            'template_code' => is_string($templateCode) && $templateCode !== '' ? $templateCode : null,
            'category' => is_string($category) && $category !== '' ? $category : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function firstListTemplateItem(array $body, array $data): array
    {
        $candidates = [
            Arr::get($body, 'ListTemplate'),
            Arr::get($body, 'Data.ListTemplate'),
            Arr::get($body, 'data.ListTemplate'),
            Arr::get($data, 'ListTemplate'),
            Arr::get($body, 'body.ListTemplate'),
            Arr::get($body, 'body.Data.ListTemplate'),
        ];

        foreach ($candidates as $list) {
            if (! is_array($list) || $list === []) {
                continue;
            }

            $first = $list[0] ?? null;
            if (is_array($first)) {
                return $first;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function extractDetailPayload(array $body): array
    {
        foreach (['data', 'Data'] as $key) {
            if (isset($body[$key]) && is_array($body[$key])) {
                return $body[$key];
            }
        }

        if (isset($body['body']['data']) && is_array($body['body']['data'])) {
            return $body['body']['data'];
        }

        if (isset($body['body']['Data']) && is_array($body['body']['Data'])) {
            return $body['body']['Data'];
        }

        return [];
    }

    /**
     * Map CAMS/Meta category onto our catalog values without clobbering LTO/Carousel.
     */
    private function normalizeRemoteCategory(?string $remoteCategory, string $localCategory): ?string
    {
        if ($remoteCategory === null || $remoteCategory === '') {
            return null;
        }

        $remote = strtoupper(trim($remoteCategory));
        $local = strtoupper(trim($localCategory));

        // WhatsApp stores LTO/Carousel as MARKETING — keep the richer local category.
        if ($remote === TemplateCategoryCatalog::MARKETING
            && in_array($local, [TemplateCategoryCatalog::LIMITED_TIME_OFFER, TemplateCategoryCatalog::CAROUSEL], true)) {
            return null;
        }

        return match ($remote) {
            TemplateCategoryCatalog::MARKETING,
            TemplateCategoryCatalog::UTILITY,
            TemplateCategoryCatalog::AUTHENTICATION => $remote,
            'SERVICE' => TemplateCategoryCatalog::UTILITY,
            default => null,
        };
    }

    /**
     * Map WhatsApp / Alibaba AuditStatus → TemplateStatus (legacy + webhook aliases).
     *
     * @return array{0: TemplateStatus, 1: string|null}
     */
    public function mapAuditStatus(?string $status, ?string $reason = null): array
    {
        $normalized = strtolower(trim((string) $status));
        $normalized = str_replace(['_', ' '], '', $normalized);

        return match ($normalized) {
            'pass', 'approved', 'success' => [TemplateStatus::Approved, null],
            'fail', 'failed', 'rejected' => [TemplateStatus::Rejected, $reason ? trim($reason) : null],
            'sendfail' => [TemplateStatus::Rejected, $reason ? trim($reason) : null],
            'auditing', 'unaudit', 'pending', 'pendingreview' => [TemplateStatus::PendingReview, null],
            default => [TemplateStatus::PendingReview, null],
        };
    }

    private function normalizeName(string $name): string
    {
        return str_replace(' ', '_', strtolower(trim($name)));
    }
}
