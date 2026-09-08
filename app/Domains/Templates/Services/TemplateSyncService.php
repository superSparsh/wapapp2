<?php

declare(strict_types=1);

namespace App\Domains\Templates\Services;

use App\Domains\Templates\Enums\TemplateStatus;
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
     */
    public function syncBatch(int $limit = 50): array
    {
        $templates = Template::query()
            ->where('status', TemplateStatus::PendingReview)
            ->whereNotNull('code')
            ->orderBy('synced_at')
            ->limit($limit)
            ->get();

        $processed = 0;
        $success = 0;
        $errors = 0;

        foreach ($templates as $template) {
            try {
                $processed++;
                $this->syncTemplateStatus($template);
                $success++;
            } catch (\Throwable $e) {
                $errors++;
                Log::warning('Template sync failed', [
                    'template_id' => $template->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return compact('processed', 'success', 'errors');
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
     * Sync a single template's status from WhatsApp.
     */
    private function syncTemplateStatus(Template $template): void
    {
        $line = $template->whatsappLine;
        if (! $line instanceof WhatsappLine || blank($line->alibaba_cust_space_id)) {
            return;
        }

        try {
            $params = ['CustSpaceId' => $line->alibaba_cust_space_id];

            if ($template->code) {
                $params['TemplateCode'] = $template->code;
                $response = $this->camsClient->getChatappTemplateDetail($params);
            } else {
                // For new templates without a code, list by name
                $params['Name'] = $this->normalizeName($template->code ?: $template->name);
                $response = $this->camsClient->listTemplates($params);
            }

            if (! $response->successful()) {
                return;
            }

            $body = $response->json();

            // For detail response
            $auditStatus = $body['data']['auditStatus'] ?? $body['AuditStatus'] ?? null;
            $reason = $body['data']['reason'] ?? $body['Reason'] ?? null;
            $templateCode = $body['data']['templateCode'] ?? $body['TemplateCode'] ?? $body['ListTemplate'][0]['TemplateCode'] ?? null;

            // For list response
            if (! $auditStatus && ! empty($body['ListTemplate'][0])) {
                $auditStatus = $body['ListTemplate'][0]['AuditStatus'] ?? null;
                $templateCode = $body['ListTemplate'][0]['TemplateCode'] ?? $templateCode;
            }

            if (! $auditStatus) {
                return;
            }

            [$newStatus, $rejectionReason] = $this->mapAuditStatus($auditStatus, $reason);

            $previous = $template->status;

            $updateData = [
                'status' => $newStatus,
                'synced_at' => now(),
            ];

            if ($templateCode) {
                $updateData['code'] = $templateCode;
            }

            if ($rejectionReason) {
                $updateData['rejection_reason'] = $rejectionReason;
            }

            $template->update($updateData);

            if ($previous !== $newStatus) {
                TemplateStatusLog::query()->create([
                    'template_id' => $template->id,
                    'previous_status' => $previous->value,
                    'new_status' => $newStatus->value,
                    'reason' => $rejectionReason,
                    'meta' => ['audit_status' => $auditStatus],
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Template status sync failed', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);
        }
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
