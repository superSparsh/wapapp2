<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Handlers;

use App\Domains\Templates\Enums\TemplateStatus;
use App\Domains\Webhooks\Parsers\AlibabaWebhookParser;
use App\Models\InboundWebhookEvent;
use App\Models\Template;
use App\Models\TemplateStatusLog;
use App\Models\Tenant;
use App\Models\WhatsappLine;
use Illuminate\Support\Facades\Log;

/**
 * Handles Alibaba CAMS template audit callbacks (when present on status uplink).
 * Primary approval path remains templates:sync-statuses polling; this is a fast-path.
 */
class TemplateAuditWebhookHandler
{
    public function __construct(
        private readonly AlibabaWebhookParser $parser,
    ) {}

    public function looksLikeTemplateAudit(array $item): bool
    {
        $code = (string) ($item['TemplateCode'] ?? $item['templateCode'] ?? '');
        $audit = (string) ($item['AuditStatus'] ?? $item['auditStatus'] ?? $item['TemplateStatus'] ?? $item['templateStatus'] ?? '');
        $messageId = (string) ($item['MessageId'] ?? $item['messageId'] ?? '');

        return $code !== '' && $audit !== '' && $messageId === '';
    }

    public function handle(InboundWebhookEvent $event): void
    {
        $items = $this->parser->parsePayload($event->payload);
        $item = $this->parser->firstItem($items);

        if ($item === null || ! $this->looksLikeTemplateAudit($item)) {
            throw new \RuntimeException('Template audit payload is invalid.');
        }

        $templateCode = (string) ($item['TemplateCode'] ?? $item['templateCode'] ?? '');
        $auditStatus = strtolower((string) ($item['AuditStatus'] ?? $item['auditStatus'] ?? $item['TemplateStatus'] ?? $item['templateStatus'] ?? ''));
        $custSpaceId = (string) ($item['CustSpaceId'] ?? $item['custSpaceId'] ?? '');
        $reason = (string) ($item['Reason'] ?? $item['reason'] ?? $item['RejectReason'] ?? $item['rejectReason'] ?? '');

        $matched = false;

        foreach (Tenant::query()->cursor() as $tenant) {
            tenancy()->initialize($tenant);

            try {
                if ($custSpaceId !== '' && ! $this->tenantOwnsCustSpace($custSpaceId)) {
                    continue;
                }

                $template = Template::query()->where('code', $templateCode)->first();
                if ($template === null) {
                    continue;
                }

                $this->applyAudit($template, $auditStatus, $reason, $templateCode);
                $event->forceFill(['tenant_id' => $tenant->id])->save();
                $matched = true;
                break;
            } finally {
                tenancy()->end();
            }
        }

        if (! $matched) {
            Log::warning('Template audit webhook: template not found in any tenant', [
                'template_code' => $templateCode,
                'cust_space_id' => $custSpaceId,
                'audit_status' => $auditStatus,
            ]);
        }
    }

    private function tenantOwnsCustSpace(string $custSpaceId): bool
    {
        return WhatsappLine::query()
            ->where('alibaba_cust_space_id', $custSpaceId)
            ->exists();
    }

    private function applyAudit(Template $template, string $auditStatus, string $reason, string $templateCode): void
    {
        $newStatus = match (true) {
            in_array($auditStatus, ['pass', 'approved', 'success'], true) => TemplateStatus::Approved,
            in_array($auditStatus, ['fail', 'failed', 'rejected', 'sendfail'], true) => TemplateStatus::Rejected,
            default => TemplateStatus::PendingReview,
        };

        $previous = $template->status;
        $updates = [
            'status' => $newStatus,
            'synced_at' => now(),
        ];

        if ($newStatus === TemplateStatus::Rejected && $reason !== '') {
            $updates['rejection_reason'] = mb_substr($reason, 0, 500);
        }

        if ($newStatus === TemplateStatus::Approved) {
            $updates['rejection_reason'] = null;
        }

        $template->update($updates);

        if ($previous !== $newStatus) {
            TemplateStatusLog::query()->create([
                'template_id' => $template->id,
                'previous_status' => $previous->value,
                'new_status' => $newStatus->value,
                'reason' => $reason !== '' ? $reason : 'alibaba_webhook_audit',
                'meta' => ['template_code' => $templateCode, 'audit_status' => $auditStatus],
            ]);
        }
    }
}
