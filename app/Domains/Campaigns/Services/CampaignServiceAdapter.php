<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Services;

use App\Models\Campaign;
use App\Models\CampaignWebhook;
use App\Models\Template;
use App\Models\WhatsappLine;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CampaignServiceAdapter
{
    public function __construct(
        private readonly CampaignService $localCampaignService,
        private readonly CampaignQueryService $localQueryService,
        private readonly CampaignStatsService $localStatsService,
        private readonly CampaignCostCalculator $localCostCalculator,
        private readonly CampaignResendService $localResendService,
        private readonly CampaignCsvImportService $localCsvImportService,
        private readonly CampaignWebhookService $localWebhookService,
        private readonly CampaignTestMessageService $localTestMessageService,
        private readonly CampaignSendService $localSendService,
    ) {}

    public function paginate(
        int $perPage = 10,
        ?string $search = null,
        ?string $status = null,
        string $sort = 'created_at',
        string $direction = 'desc',
    ): LengthAwarePaginator {
        $this->localSendService->reconcileStuckSendingCampaigns();

        return $this->localQueryService->paginate(
            perPage: $perPage,
            search: $search,
            status: $status,
            sort: $sort,
            direction: $direction,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Campaign
    {
        $data = $this->enrichCamsContext($data);

        return $this->localCampaignService->create($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Campaign $campaign, array $data): Campaign
    {
        $data = $this->enrichCamsContext($data, $campaign);

        return $this->localCampaignService->update($campaign, $data);
    }

    public function delete(Campaign $campaign): void
    {
        $this->localCampaignService->delete($campaign);
    }

    public function launch(Campaign $campaign): Campaign
    {
        $this->persistCamsContext($campaign);

        return $this->localCampaignService->launch($campaign);
    }

    public function toggle(Campaign $campaign): Campaign
    {
        return $this->localCampaignService->toggle($campaign);
    }

    public function duplicate(Campaign $campaign): Campaign
    {
        return $this->localCampaignService->duplicate($campaign);
    }

    /**
     * @param array<string, mixed> $templateVariables
     */
    public function sendTestMessage(Campaign $campaign, string $phone, array $templateVariables = []): void
    {
        $this->localTestMessageService->send($campaign, $phone, $templateVariables);
    }

    public function resendFailed(Campaign $campaign): int
    {
        return $this->localResendService->resendFailed($campaign);
    }

    /**
     * @return array{list: \App\Models\MailList, campaign: Campaign, imported: int, launched: bool}
     */
    public function createCampaignFromFailed(
        Campaign $campaign,
        string $listName,
        string $campaignName,
        string $sendOption = 'now',
    ): array {
        return $this->localResendService->createCampaignFromFailed(
            $campaign,
            $listName,
            $campaignName,
            $sendOption,
        );
    }

    /**
     * @return array{attempted: int, sent: int, skipped: int}
     */
    public function resendOptInToFailed(Campaign $campaign): array
    {
        return $this->localResendService->resendOptInToFailed($campaign);
    }

    /**
     * @return array{recipients: int, unit_cost: float, total_cost: float, currency: string}
     */
    public function calculateCost(Campaign $campaign): array
    {
        return $this->localCostCalculator->estimate($campaign);
    }

    /**
     * @return array{imported: int, skipped: int}
     */
    public function importRecipients(Campaign $campaign, UploadedFile $file): array
    {
        return $this->localCsvImportService->import($campaign, $file);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function storeWebhook(Campaign $campaign, array $data): CampaignWebhook
    {
        return $this->localWebhookService->store($campaign, $data);
    }

    /**
     * @return array{total: int, sent: int, delivered: int, failed: int, read: int, response: int, unsubscribed: int, delivered_pct: string, failed_pct: string, read_pct: string, response_pct: string, unsubscribed_pct: string}
     */
    public function getGaugeMetrics(Campaign $campaign): array
    {
        return $this->localStatsService->gaugeMetrics($campaign);
    }

    public function recipientLog(Campaign $campaign, int $perPage = 10, ?string $status = null): LengthAwarePaginator
    {
        return $this->localStatsService->recipientLog($campaign, $perPage, $status);
    }

    public function exportCsv(Campaign $campaign, ?string $status = null): StreamedResponse
    {
        return $this->localStatsService->exportCsv($campaign, $status);
    }

    public function exportFullReport(Campaign $campaign): StreamedResponse
    {
        return $this->localStatsService->exportFullReport($campaign);
    }

    /**
     * Embed line/template CAMS fields into template_variables for the microservice.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function enrichCamsContext(array $data, ?Campaign $campaign = null): array
    {
        $vars = (array) ($data['template_variables'] ?? $campaign?->template_variables ?? []);

        $lineId = $data['whatsapp_line_id'] ?? $campaign?->whatsapp_line_id;
        if ($lineId) {
            $line = WhatsappLine::query()->find($lineId);
            if ($line !== null) {
                $vars['line_phone'] = $line->phone;
                $vars['cust_space_id'] = $line->alibaba_cust_space_id;
            }
        }

        $templateId = $data['template_id'] ?? $campaign?->template_id;
        if ($templateId) {
            $template = Template::query()->find($templateId);
            if ($template !== null) {
                $vars['template_code'] = $template->code ?: ($vars['template_code'] ?? null);
                $vars['language'] = $template->language ?: ($vars['language'] ?? 'en_GB');
            }
        }

        $data['template_variables'] = $vars;

        return $data;
    }

    private function persistCamsContext(Campaign $campaign): void
    {
        $enriched = $this->enrichCamsContext([], $campaign);
        $vars = (array) ($enriched['template_variables'] ?? []);
        $campaign->forceFill(['template_variables' => $vars])->save();
        $campaign->refresh();
    }
}
