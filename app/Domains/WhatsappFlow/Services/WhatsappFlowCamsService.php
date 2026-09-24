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
        return $this->syncJsonAssetDetailed($flow)['ok'];
    }

    /**
     * @return array{ok: bool, message: string, file_path: string|null, response: mixed}
     */
    public function syncJsonAssetDetailed(WhatsappFlow $flow): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'CAMS credentials are not configured.', 'file_path' => null, 'response' => null];
        }

        if (blank($flow->meta_flow_id)) {
            return ['ok' => false, 'message' => 'Remote WhatsApp Flow ID is missing. Re-create the flow or check CAMS CreateFlow.', 'file_path' => null, 'response' => null];
        }

        if (blank($flow->json_asset_path)) {
            return ['ok' => false, 'message' => 'Flow JSON asset path is missing. Save the draft again.', 'file_path' => null, 'response' => null];
        }

        $custSpaceId = $this->resolveCustSpaceId($flow);

        if ($custSpaceId === null) {
            return ['ok' => false, 'message' => 'Cust Space ID is missing on the WhatsApp line.', 'file_path' => null, 'response' => null];
        }

        $filePath = $this->assetService->publicUrl((string) $flow->json_asset_path, $flow);

        Log::info('CAMS UpdateFlowJSONAsset starting', [
            'flow_id' => $flow->id,
            'meta_flow_id' => $flow->meta_flow_id,
            'file_path' => $filePath,
        ]);

        $response = $this->client->updateFlowJsonAsset([
            'FlowId' => (string) $flow->meta_flow_id,
            'FilePath' => $filePath,
            'CustSpaceId' => $custSpaceId,
        ]);

        $json = $response->json();
        $code = is_array($json) ? ($json['Code'] ?? $json['code'] ?? null) : null;
        $codeOk = ! is_scalar($code) || strtoupper((string) $code) === 'OK';
        $ok = $response->successful() && $codeOk;
        $validationMessage = $this->extractValidationErrorsMessage($json);

        if (! $ok || $validationMessage !== null) {
            $message = is_array($json)
                ? (string) ($json['Message'] ?? $json['message'] ?? $response->body())
                : $response->body();

            if ($validationMessage !== null) {
                $message = trim($message.' '.$validationMessage);
            }

            Log::warning('CAMS UpdateFlowJSONAsset failed', [
                'flow_id' => $flow->id,
                'meta_flow_id' => $flow->meta_flow_id,
                'file_path' => $filePath,
                'http_status' => $response->status(),
                'code' => is_scalar($code) ? (string) $code : null,
                'validation' => $validationMessage,
                'body' => $response->body(),
            ]);

            return [
                'ok' => false,
                'message' => $message !== '' ? $message : 'CAMS UpdateFlowJSONAsset failed.',
                'file_path' => $filePath,
                'response' => $json,
            ];
        }

        return ['ok' => true, 'message' => 'ok', 'file_path' => $filePath, 'response' => $json];
    }

    public function publishRemote(WhatsappFlow $flow): bool
    {
        return $this->publishRemoteDetailed($flow)['ok'];
    }

    /**
     * @return array{ok: bool, message: string, response: mixed}
     */
    public function publishRemoteDetailed(WhatsappFlow $flow): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'CAMS credentials are not configured.', 'response' => null];
        }

        if (blank($flow->meta_flow_id)) {
            return ['ok' => false, 'message' => 'Remote WhatsApp Flow ID is missing.', 'response' => null];
        }

        $custSpaceId = $this->resolveCustSpaceId($flow);

        if ($custSpaceId === null) {
            return ['ok' => false, 'message' => 'Cust Space ID is missing on the WhatsApp line.', 'response' => null];
        }

        $response = $this->client->publishFlow([
            'FlowId' => (string) $flow->meta_flow_id,
            'CustSpaceId' => $custSpaceId,
        ]);

        $parsed = $this->parseCamsResponse($response);

        if (! $parsed['ok']) {
            Log::warning('CAMS PublishFlow failed', [
                'flow_id' => $flow->id,
                'meta_flow_id' => $flow->meta_flow_id,
                'cust_space_id' => $custSpaceId,
                'http_status' => $response->status(),
                'code' => $parsed['code'],
                'body' => $response->body(),
            ]);
        }

        return [
            'ok' => $parsed['ok'],
            'message' => $parsed['message'],
            'response' => $parsed['json'],
        ];
    }

    /**
     * @return array{ok: bool, code: string|null, message: string, json: mixed}
     */
    private function parseCamsResponse(\Illuminate\Http\Client\Response $response): array
    {
        $json = $response->json();
        $code = is_array($json) ? ($json['Code'] ?? $json['code'] ?? null) : null;
        $codeOk = ! is_scalar($code) || strtoupper((string) $code) === 'OK';
        $ok = $response->successful() && $codeOk;
        $message = is_array($json)
            ? (string) ($json['Message'] ?? $json['message'] ?? $response->body())
            : $response->body();

        if ($ok) {
            $message = 'ok';
        } elseif ($message === '') {
            $message = 'CAMS request failed.';
        }

        return [
            'ok' => $ok,
            'code' => is_scalar($code) ? (string) $code : null,
            'message' => $message,
            'json' => $json,
        ];
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

    /**
     * Meta / CAMS may accept the upload (Code=OK) while still returning validation_errors.
     * Surface those so publish doesn't fail later with opaque 139002.
     *
     * @param  mixed  $json
     */
    private function extractValidationErrorsMessage(mixed $json): ?string
    {
        if (! is_array($json)) {
            return null;
        }

        $candidates = [
            Arr::get($json, 'Data.ValidationErrors'),
            Arr::get($json, 'Data.validation_errors'),
            Arr::get($json, 'Data.validationErrors'),
            Arr::get($json, 'validation_errors'),
            Arr::get($json, 'ValidationErrors'),
            Arr::get($json, 'data.validation_errors'),
            Arr::get($json, 'data.ValidationErrors'),
        ];

        foreach ($candidates as $errors) {
            if (! is_array($errors) || $errors === []) {
                continue;
            }

            $parts = [];
            foreach ($errors as $error) {
                if (is_string($error) && trim($error) !== '') {
                    $parts[] = trim($error);

                    continue;
                }

                if (! is_array($error)) {
                    continue;
                }

                $msg = (string) ($error['message'] ?? $error['Message'] ?? $error['error'] ?? $error['Error'] ?? '');
                $path = (string) ($error['path'] ?? $error['Path'] ?? '');
                $line = trim($msg.($path !== '' ? ' ('.$path.')' : ''));
                if ($line !== '') {
                    $parts[] = $line;
                }
            }

            if ($parts !== []) {
                return 'Validation: '.implode('; ', array_slice($parts, 0, 5));
            }
        }

        return null;
    }
}
