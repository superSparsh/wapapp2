<?php

declare(strict_types=1);

namespace App\Domains\WhatsappFlow\Services;

use App\Domains\WhatsApp\Services\AlibabaCamsClient;
use App\Models\WhatsappFlow;
use App\Models\WhatsappLine;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class WhatsappFlowCamsService
{
    public function __construct(
        private readonly AlibabaCamsClient $client,
        private readonly WhatsappFlowAssetService $assetService,
    ) {}

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    public function resolveCustSpaceId(WhatsappFlow $flow): ?string
    {
        if (filled($flow->cust_space_id)) {
            return (string) $flow->cust_space_id;
        }

        if ($flow->whatsapp_line_id) {
            $line = WhatsappLine::query()->find($flow->whatsapp_line_id);

            if ($line !== null && filled($line->alibaba_cust_space_id)) {
                return (string) $line->alibaba_cust_space_id;
            }
        }

        return WhatsappLine::query()
            ->where('is_default', true)
            ->value('alibaba_cust_space_id');
    }

    /**
     * @param  array<int, string>  $categories
     */
    public function createRemote(WhatsappFlow $flow, array $categories = ['OTHER']): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $custSpaceId = $this->resolveCustSpaceId($flow);

        if ($custSpaceId === null) {
            return null;
        }

        $response = $this->client->createFlow([
            'FlowName' => $flow->name,
            'CustSpaceId' => $custSpaceId,
            'Categories' => json_encode($categories, JSON_THROW_ON_ERROR),
        ]);

        return $this->extractFlowId($response);
    }

    public function syncJsonAsset(WhatsappFlow $flow): bool
    {
        if (! $this->isConfigured() || blank($flow->meta_flow_id) || blank($flow->json_asset_path)) {
            return false;
        }

        $custSpaceId = $this->resolveCustSpaceId($flow);

        if ($custSpaceId === null) {
            return false;
        }

        $response = $this->client->updateFlowJsonAsset([
            'FlowId' => (string) $flow->meta_flow_id,
            'FilePath' => $this->assetService->publicUrl((string) $flow->json_asset_path),
            'CustSpaceId' => $custSpaceId,
        ]);

        return $response->successful();
    }

    public function publishRemote(WhatsappFlow $flow): bool
    {
        if (! $this->isConfigured() || blank($flow->meta_flow_id)) {
            return false;
        }

        $custSpaceId = $this->resolveCustSpaceId($flow);

        if ($custSpaceId === null) {
            return false;
        }

        $response = $this->client->publishFlow([
            'FlowId' => (string) $flow->meta_flow_id,
            'CustSpaceId' => $custSpaceId,
        ]);

        return $response->successful();
    }

    public function deprecateRemote(WhatsappFlow $flow): bool
    {
        if (! $this->isConfigured() || blank($flow->meta_flow_id)) {
            return true;
        }

        $custSpaceId = $this->resolveCustSpaceId($flow);

        if ($custSpaceId === null) {
            return false;
        }

        $response = $this->client->deprecateFlow([
            'FlowId' => (string) $flow->meta_flow_id,
            'CustSpaceId' => $custSpaceId,
        ]);

        return $response->successful();
    }

    public function deleteRemote(WhatsappFlow $flow): bool
    {
        if (! $this->isConfigured() || blank($flow->meta_flow_id)) {
            return true;
        }

        $custSpaceId = $this->resolveCustSpaceId($flow);

        if ($custSpaceId === null) {
            return false;
        }

        $response = $this->client->deleteFlow([
            'FlowId' => (string) $flow->meta_flow_id,
            'CustSpaceId' => $custSpaceId,
        ]);

        return $response->successful();
    }

    public function previewUrl(WhatsappFlow $flow): ?string
    {
        if (! $this->isConfigured() || blank($flow->meta_flow_id)) {
            return null;
        }

        $custSpaceId = $this->resolveCustSpaceId($flow);

        if ($custSpaceId === null) {
            return null;
        }

        $response = $this->client->getFlowPreviewUrl([
            'FlowId' => (string) $flow->meta_flow_id,
            'CustSpaceId' => $custSpaceId,
        ]);

        if (! $response->successful()) {
            Log::warning('WhatsApp Flow preview URL request failed', [
                'flow_id' => $flow->id,
                'body' => $response->body(),
            ]);

            return null;
        }

        return Arr::get($response->json(), 'Data.PreviewUrl')
            ?? Arr::get($response->json(), 'Data.previewUrl')
            ?? Arr::get($response->json(), 'data.PreviewUrl')
            ?? Arr::get($response->json(), 'data.previewUrl')
            ?? Arr::get($response->json(), 'body.data.previewUrl')
            ?? Arr::get($response->json(), 'Body.Data.PreviewUrl');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRemoteFlows(?string $custSpaceId = null): array
    {
        if (! $this->isConfigured() || $custSpaceId === null) {
            return [];
        }

        $response = $this->client->listFlows(['CustSpaceId' => $custSpaceId]);

        if (! $response->successful()) {
            return [];
        }

        $flows = Arr::get($response->json(), 'Data.FlowList', Arr::get($response->json(), 'data.flowList', []));

        return is_array($flows) ? $flows : [];
    }

    private function extractFlowId(Response $response): ?string
    {
        if (! $response->successful()) {
            Log::warning('CAMS createFlow failed', ['body' => $response->body()]);

            return null;
        }

        $flowId = Arr::get($response->json(), 'Data.FlowId')
            ?? Arr::get($response->json(), 'data.flowId');

        return $flowId !== null ? (string) $flowId : null;
    }
}
