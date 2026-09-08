<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Services;

use App\Domains\Campaigns\Contracts\CampaignServiceClientInterface;
use App\Models\Campaign;
use App\Models\CampaignWebhook;
use App\Models\Template;
use App\Models\WhatsappLine;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class CampaignServiceAdapter
{
    public function __construct(
        private readonly CampaignServiceClientInterface $client,
        private readonly CampaignService $localCampaignService,
        private readonly CampaignQueryService $localQueryService,
        private readonly CampaignStatsService $localStatsService,
        private readonly CampaignCostCalculator $localCostCalculator,
        private readonly CampaignResendService $localResendService,
        private readonly CampaignCsvImportService $localCsvImportService,
        private readonly CampaignWebhookService $localWebhookService,
        private readonly CampaignTestMessageService $localTestMessageService,
    ) {}

    public function isMicroserviceEnabled(): bool
    {
        return (bool) config('campaign-service.enabled', false);
    }

    public function shouldFallback(): bool
    {
        return (bool) config('campaign-service.fallback_to_local', true);
    }

    public function paginate(
        int $perPage = 10,
        ?string $search = null,
        ?string $status = null,
        string $sort = 'created_at',
        string $direction = 'desc',
    ): LengthAwarePaginator {
        if ($this->isMicroserviceEnabled()) {
            try {
                $response = $this->client->listCampaigns([
                    'per_page' => $perPage,
                    'search' => $search,
                    'status' => $status,
                    'sort' => $sort,
                    'direction' => $direction,
                ]);

                $items = collect($response['items'] ?? [])->map(function (array $item) {
                    $campaign = new Campaign;
                    $campaign->forceFill($item);
                    $campaign->exists = true;

                    return $campaign;
                });

                $meta = $response['meta'] ?? [];
                $total = (int) ($meta['total'] ?? $items->count());
                $currentPage = (int) ($meta['current_page'] ?? 1);

                return new \Illuminate\Pagination\LengthAwarePaginator(
                    $items,
                    $total,
                    $perPage,
                    $currentPage,
                    ['path' => request()->url(), 'query' => request()->query()]
                );
            } catch (Throwable $e) {
                Log::warning('Failed fetching campaigns from microservice, attempting fallback', [
                    'error' => $e->getMessage(),
                ]);

                if (! $this->shouldFallback()) {
                    throw $e;
                }
            }
        }

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

        if ($this->isMicroserviceEnabled()) {
            try {
                $response = $this->client->createCampaign($data);
                if (isset($response['campaign'])) {
                    $campaign = new Campaign;
                    $campaign->forceFill((array) $response['campaign']);
                    $campaign->exists = true;

                    return $campaign;
                }
            } catch (Throwable $e) {
                Log::warning('Failed creating campaign in microservice, attempting fallback', [
                    'error' => $e->getMessage(),
                ]);

                if (! $this->shouldFallback()) {
                    throw $e;
                }
            }
        }

        return $this->localCampaignService->create($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Campaign $campaign, array $data): Campaign
    {
        $data = $this->enrichCamsContext($data, $campaign);

        if ($this->isMicroserviceEnabled()) {
            try {
                $uuid = $campaign->uuid ?? (string) $campaign->id;
                $response = $this->client->updateCampaign($uuid, $data);
                if (isset($response['campaign'])) {
                    $campaign->forceFill((array) $response['campaign']);

                    return $campaign;
                }
            } catch (Throwable $e) {
                Log::warning('Failed updating campaign in microservice, attempting fallback', [
                    'error' => $e->getMessage(),
                ]);

                if (! $this->shouldFallback()) {
                    throw $e;
                }
            }
        }

        return $this->localCampaignService->update($campaign, $data);
    }

    public function delete(Campaign $campaign): void
    {
        if ($this->isMicroserviceEnabled()) {
            try {
                $uuid = $campaign->uuid ?? (string) $campaign->id;
                $this->client->deleteCampaign($uuid);
            } catch (Throwable $e) {
                Log::warning('Failed deleting campaign in microservice, attempting fallback', [
                    'error' => $e->getMessage(),
                ]);

                if (! $this->shouldFallback()) {
                    throw $e;
                }
            }
        }

        $this->localCampaignService->delete($campaign);
    }

    public function launch(Campaign $campaign): Campaign
    {
        $this->persistCamsContext($campaign);

        if ($this->isMicroserviceEnabled()) {
            try {
                $uuid = $campaign->uuid ?? (string) $campaign->id;
                // Keep microservice copy in sync with line/template CAMS context.
                $this->client->updateCampaign($uuid, [
                    'template_variables' => $campaign->template_variables,
                    'whatsapp_line_id' => $campaign->whatsapp_line_id,
                    'template_id' => $campaign->template_id,
                ]);
                $response = $this->client->launchCampaign($uuid);
                if (isset($response['campaign'])) {
                    $campaign->forceFill((array) $response['campaign']);

                    return $campaign;
                }
            } catch (Throwable $e) {
                Log::warning('Failed launching campaign in microservice, attempting fallback', [
                    'error' => $e->getMessage(),
                ]);

                if (! $this->shouldFallback()) {
                    throw $e;
                }
            }
        }

        return $this->localCampaignService->launch($campaign);
    }

    public function toggle(Campaign $campaign): Campaign
    {
        if ($this->isMicroserviceEnabled()) {
            try {
                $uuid = $campaign->uuid ?? (string) $campaign->id;
                $response = $this->client->toggleCampaign($uuid);
                if (isset($response['campaign'])) {
                    $campaign->forceFill((array) $response['campaign']);

                    return $campaign;
                }
            } catch (Throwable $e) {
                Log::warning('Failed toggling campaign in microservice, attempting fallback', [
                    'error' => $e->getMessage(),
                ]);

                if (! $this->shouldFallback()) {
                    throw $e;
                }
            }
        }

        return $this->localCampaignService->toggle($campaign);
    }

    public function duplicate(Campaign $campaign): Campaign
    {
        if ($this->isMicroserviceEnabled()) {
            try {
                $uuid = $campaign->uuid ?? (string) $campaign->id;
                $response = $this->client->duplicateCampaign($uuid);
                if (isset($response['campaign'])) {
                    $new = new Campaign;
                    $new->forceFill((array) $response['campaign']);
                    $new->exists = true;

                    return $new;
                }
            } catch (Throwable $e) {
                Log::warning('Failed duplicating campaign in microservice, attempting fallback', [
                    'error' => $e->getMessage(),
                ]);

                if (! $this->shouldFallback()) {
                    throw $e;
                }
            }
        }

        return $this->localCampaignService->duplicate($campaign);
    }

    /**
     * @param array<string, mixed> $templateVariables
     */
    public function sendTestMessage(Campaign $campaign, string $phone, array $templateVariables = []): void
    {
        if ($this->isMicroserviceEnabled()) {
            try {
                $uuid = $campaign->uuid ?? (string) $campaign->id;
                $this->client->sendTestMessage($uuid, $phone, $templateVariables);

                return;
            } catch (Throwable $e) {
                Log::warning('Failed sending test message via microservice, attempting fallback', [
                    'error' => $e->getMessage(),
                ]);

                if (! $this->shouldFallback()) {
                    throw $e;
                }
            }
        }

        $this->localTestMessageService->send($campaign, $phone, $templateVariables);
    }

    public function resendFailed(Campaign $campaign): int
    {
        if ($this->isMicroserviceEnabled()) {
            try {
                $uuid = $campaign->uuid ?? (string) $campaign->id;
                $response = $this->client->resendFailed($uuid);

                return (int) ($response['resent'] ?? 0);
            } catch (Throwable $e) {
                Log::warning('Failed resending failed campaign recipients via microservice, attempting fallback', [
                    'error' => $e->getMessage(),
                ]);

                if (! $this->shouldFallback()) {
                    throw $e;
                }
            }
        }

        return $this->localResendService->resendFailed($campaign);
    }

    /**
     * @return array{recipients: int, unit_cost: float, total_cost: float, currency: string}
     */
    public function calculateCost(Campaign $campaign): array
    {
        if ($this->isMicroserviceEnabled()) {
            try {
                $uuid = $campaign->uuid ?? (string) $campaign->id;
                $response = $this->client->calculateCost($uuid, $campaign->template?->category);

                return [
                    'recipients' => (int) ($response['recipients'] ?? 0),
                    'unit_cost' => (float) ($response['unit_cost'] ?? 0.0),
                    'total_cost' => (float) ($response['total_cost'] ?? 0.0),
                    'currency' => (string) ($response['currency'] ?? 'INR'),
                ];
            } catch (Throwable $e) {
                Log::warning('Failed calculating campaign cost via microservice, attempting fallback', [
                    'error' => $e->getMessage(),
                ]);

                if (! $this->shouldFallback()) {
                    throw $e;
                }
            }
        }

        return $this->localCostCalculator->estimate($campaign);
    }

    /**
     * @return array{imported: int, skipped: int}
     */
    public function importRecipients(Campaign $campaign, UploadedFile $file): array
    {
        if ($this->isMicroserviceEnabled()) {
            try {
                $uuid = $campaign->uuid ?? (string) $campaign->id;
                $response = $this->client->importRecipients($uuid, $file);

                return [
                    'imported' => (int) ($response['imported'] ?? 0),
                    'skipped' => (int) ($response['skipped'] ?? 0),
                ];
            } catch (Throwable $e) {
                Log::warning('Failed importing campaign recipients via microservice, attempting fallback', [
                    'error' => $e->getMessage(),
                ]);

                if (! $this->shouldFallback()) {
                    throw $e;
                }
            }
        }

        return $this->localCsvImportService->import($campaign, $file);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function storeWebhook(Campaign $campaign, array $data): CampaignWebhook
    {
        if ($this->isMicroserviceEnabled()) {
            try {
                $uuid = $campaign->uuid ?? (string) $campaign->id;
                $response = $this->client->storeWebhook($uuid, $data);
                if (isset($response['webhook'])) {
                    $webhook = new CampaignWebhook;
                    $webhook->forceFill((array) $response['webhook']);
                    $webhook->exists = true;

                    return $webhook;
                }
            } catch (Throwable $e) {
                Log::warning('Failed storing campaign webhook via microservice, attempting fallback', [
                    'error' => $e->getMessage(),
                ]);

                if (! $this->shouldFallback()) {
                    throw $e;
                }
            }
        }

        return $this->localWebhookService->store($campaign, $data);
    }

    /**
     * @return array{total: int, sent: int, delivered: int, failed: int, read: int, response: int, unsubscribed: int, delivered_pct: string, failed_pct: string, read_pct: string, response_pct: string, unsubscribed_pct: string}
     */
    public function getGaugeMetrics(Campaign $campaign): array
    {
        if ($this->isMicroserviceEnabled()) {
            try {
                $uuid = $campaign->uuid ?? (string) $campaign->id;
                $response = $this->client->getStatistics($uuid);
                if (isset($response['metrics'])) {
                    return (array) $response['metrics'];
                }
            } catch (Throwable $e) {
                Log::warning('Failed fetching campaign statistics via microservice, attempting fallback', [
                    'error' => $e->getMessage(),
                ]);

                if (! $this->shouldFallback()) {
                    throw $e;
                }
            }
        }

        return $this->localStatsService->gaugeMetrics($campaign);
    }

    public function recipientLog(Campaign $campaign, int $perPage = 10, ?string $status = null): LengthAwarePaginator
    {
        if ($this->isMicroserviceEnabled()) {
            try {
                $uuid = $campaign->uuid ?? (string) $campaign->id;
                $response = $this->client->getRecipients($uuid, [
                    'per_page' => $perPage,
                    'status' => $status,
                ]);

                $items = collect($response['items'] ?? [])->map(function (array $item) {
                    $recipient = new \App\Models\CampaignRecipient;
                    $recipient->forceFill($item);
                    $recipient->exists = true;

                    return $recipient;
                });

                $meta = $response['meta'] ?? [];
                $total = (int) ($meta['total'] ?? $items->count());
                $currentPage = (int) ($meta['current_page'] ?? 1);

                return new \Illuminate\Pagination\LengthAwarePaginator(
                    $items,
                    $total,
                    $perPage,
                    $currentPage,
                    ['path' => request()->url(), 'query' => request()->query()]
                );
            } catch (Throwable $e) {
                Log::warning('Failed fetching recipient log via microservice, attempting fallback', [
                    'error' => $e->getMessage(),
                ]);

                if (! $this->shouldFallback()) {
                    throw $e;
                }
            }
        }

        return $this->localStatsService->recipientLog($campaign, $perPage, $status);
    }

    public function exportCsv(Campaign $campaign): StreamedResponse
    {
        if ($this->isMicroserviceEnabled()) {
            try {
                $uuid = $campaign->uuid ?? (string) $campaign->id;

                return $this->client->exportRecipients($uuid);
            } catch (Throwable $e) {
                Log::warning('Failed exporting campaign recipients via microservice, attempting fallback', [
                    'error' => $e->getMessage(),
                ]);

                if (! $this->shouldFallback()) {
                    throw $e;
                }
            }
        }

        return $this->localStatsService->exportCsv($campaign);
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
