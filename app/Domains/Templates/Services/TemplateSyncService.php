<?php

declare(strict_types=1);

namespace App\Domains\Templates\Services;

use App\Domains\Templates\Enums\TemplateStatus;
use App\Domains\Templates\Support\TemplateCategoryCatalog;
use App\Domains\WhatsApp\Services\AlibabaCamsClient;
use App\Models\Template;
use App\Models\TemplateStatusLog;
use App\Models\WhatsappLine;
use Illuminate\Support\Facades\Log;

class TemplateSyncService
{
    public function __construct(
        private readonly AlibabaCamsClient $camsClient,
    ) {}

    /**
     * Sync the status of the first pending template (no code yet).
     * Called by the queue worker.
     */
    public function syncFirstPending(): void
    {
        $template = Template::query()
            ->where('status', TemplateStatus::PendingReview)
            ->whereNull('code')
            ->orderBy('updated_at')
            ->first();

        if (! $template instanceof Template) {
            return;
        }

        $this->syncTemplateStatus($template);
    }

    /**
     * Batch-sync templates that have a code and are pending review.
     *
     * @return array{processed: int, success: int, errors: int, category_updates: int}
     */
    public function syncBatch(int $limit = 50): array
    {
        $templates = Template::query()
            ->where('status', TemplateStatus::PendingReview)
            ->whereNotNull('code')
            ->orderBy('synced_at')
            ->limit($limit)
            ->get();

        return $this->syncTemplates($templates);
    }

    /**
     * Daily sync: pull GetChatappTemplateDetail for coded templates and apply
     * status + category changes (Meta can reclassify Marketing → Utility, etc.).
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
            ->limit($limit)
            ->get();

        return $this->syncTemplates($templates);
    }

    /**
     * Delete the first template marked for async deletion.
     */
    public function deleteFirstReady(): void
    {
        // Soft-deleted templates with a code need API deletion
        $template = Template::query()
            ->onlyTrashed()
            ->whereNotNull('code')
            ->orderBy('deleted_at')
            ->first();

        if (! $template instanceof Template) {
            return;
        }

        $line = $template->whatsappLine;
        if (! $line instanceof WhatsappLine || blank($line->alibaba_cust_space_id)) {
            $template->forceDelete();

            return;
        }

        try {
            $response = $this->camsClient->deleteChatappTemplate([
                'TemplateCode' => $template->code,
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

        try {
            $params = ['CustSpaceId' => $line->alibaba_cust_space_id];

            if ($template->code) {
                $params['TemplateCode'] = $template->code;
                $params['Language'] = filled($template->language) ? (string) $template->language : 'en_GB';
                $response = $this->camsClient->getChatappTemplateDetail($params);
            } else {
                // For new templates without a code, list by name
                $params['Name'] = $this->normalizeName($template->code ?: $template->name);
                $params['Language'] = filled($template->language) ? (string) $template->language : 'en_GB';
                $response = $this->camsClient->listTemplates($params);
            }

            if (! $response->successful()) {
                return false;
            }

            $body = $response->json() ?? [];
            $data = $this->extractDetailPayload($body);

            $auditStatus = $data['auditStatus']
                ?? $data['AuditStatus']
                ?? $body['AuditStatus']
                ?? null;
            $reason = $data['reason'] ?? $data['Reason'] ?? $body['Reason'] ?? null;
            $templateCode = $data['templateCode']
                ?? $data['TemplateCode']
                ?? $body['TemplateCode']
                ?? null;
            $remoteCategory = $data['category'] ?? $data['Category'] ?? null;

            // For list response
            if (! $auditStatus && ! empty($body['ListTemplate'][0])) {
                $listItem = $body['ListTemplate'][0];
                $auditStatus = $listItem['AuditStatus'] ?? $listItem['auditStatus'] ?? null;
                $templateCode = $listItem['TemplateCode'] ?? $listItem['templateCode'] ?? $templateCode;
                $remoteCategory = $listItem['Category'] ?? $listItem['category'] ?? $remoteCategory;
            }

            $previousStatus = $template->status;
            $previousCategory = (string) ($template->category ?? '');
            $updateData = [
                'synced_at' => now(),
            ];

            if ($auditStatus) {
                [$newStatus, $rejectionReason] = $this->mapAuditStatus($auditStatus, is_string($reason) ? $reason : null);
                $updateData['status'] = $newStatus;

                if ($rejectionReason) {
                    $updateData['rejection_reason'] = $rejectionReason;
                }
            }

            if ($templateCode) {
                $updateData['code'] = $templateCode;
            }

            $normalizedCategory = $this->normalizeRemoteCategory(
                is_string($remoteCategory) ? $remoteCategory : null,
                $previousCategory,
            );

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
        }

        return $categoryChanged;
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
     * Map WhatsApp audit status to our TemplateStatus enum.
     *
     * @return array{0: TemplateStatus, 1: string|null}
     */
    private function mapAuditStatus(?string $status, ?string $reason): array
    {
        return match ($status) {
            'pass' => [TemplateStatus::Approved, null],
            'fail' => [TemplateStatus::Rejected, $reason ? trim($reason) : null],
            'sendFail' => [TemplateStatus::Rejected, $reason ? trim($reason) : null],
            'auditing' => [TemplateStatus::PendingReview, null],
            'unaudit' => [TemplateStatus::PendingReview, null],
            default => [TemplateStatus::PendingReview, null],
        };
    }

    private function normalizeName(string $name): string
    {
        return str_replace(' ', '_', strtolower(trim($name)));
    }
}
