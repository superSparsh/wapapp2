<?php

declare(strict_types=1);

namespace App\Domains\WhatsappFlow\Services;

use App\Events\WhatsappFlowSubmitted;
use App\Models\WhatsappFlow;
use App\Models\WhatsappFlowSubmission;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FlowDataExchangeService
{
    public function __construct(
        private readonly SubmissionProcessorService $submissionProcessor,
        private readonly WhatsappFlowQueryService $queryService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleDataExchange(WhatsappFlow $flow, array $payload): WhatsappFlowSubmission
    {
        return DB::transaction(function () use ($flow, $payload): WhatsappFlowSubmission {
            $contactPhone = (string) ($payload['phone_number'] ?? $payload['contact_phone'] ?? '');
            $formData = $payload['data'] ?? $payload['form_data'] ?? $payload;
            $flowToken = (string) ($payload['flow_token'] ?? '');

            if ($contactPhone === '' && $flowToken !== '') {
                $contactPhone = $this->extractPhoneFromToken($flowToken) ?? '';
            }

            $existing = WhatsappFlowSubmission::query()
                ->where('whatsapp_flow_id', $flow->id)
                ->where('contact_phone', $contactPhone)
                ->where('status', 'processed')
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $submission = WhatsappFlowSubmission::query()->create([
                'whatsapp_flow_id' => $flow->id,
                'contact_phone' => $contactPhone,
                'form_data' => is_array($formData) ? $formData : [],
                'status' => 'received',
            ]);

            try {
                $this->submissionProcessor->processSubmission($submission, $flow);
            } catch (\Throwable $e) {
                Log::error('WhatsApp Flow submission processing failed', [
                    'submission_id' => $submission->id,
                    'flow_id' => $flow->id,
                    'error' => $e->getMessage(),
                ]);

                $submission->markFailed();
            }

            event(new WhatsappFlowSubmitted($flow, $submission));

            return $submission;
        });
    }

    /**
     * Ingest a flow completion from an inbound WhatsApp interactive message.
     *
     * @param  array<string, mixed>  $interactivePayload
     */
    public function ingestInboundCompletion(
        WhatsappFlow $flow,
        string $contactPhone,
        array $interactivePayload,
        ?int $conversationId = null,
    ): ?WhatsappFlowSubmission {
        $responseJson = Arr::get($interactivePayload, 'nfm_reply.response_json');

        if (! is_string($responseJson) || $responseJson === '') {
            return null;
        }

        $decoded = json_decode($responseJson, true);

        if (! is_array($decoded)) {
            return null;
        }

        return $this->handleDataExchange($flow, [
            'phone_number' => $contactPhone,
            'data' => $decoded,
            'conversation_id' => $conversationId,
            'source' => 'inbound_webhook',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function buildScreenResponse(WhatsappFlow $flow, array $payload): array
    {
        $screenName = (string) ($payload['screen'] ?? '');
        $action = (string) ($payload['action'] ?? '');

        if ($action === 'data_exchange' || $action === 'navigate') {
            $nextScreen = $this->resolveNextScreen($flow, $screenName, $payload['data'] ?? []);

            if ($nextScreen !== null) {
                return [
                    'screen' => $nextScreen['id'],
                    'data' => $nextScreen['data'] ?? [],
                ];
            }
        }

        return [
            'screen' => 'SUCCESS',
            'data' => ['message' => 'Form submitted successfully.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $formData
     * @return array<string, mixed>|null
     */
    private function resolveNextScreen(WhatsappFlow $flow, string $currentScreen, array $formData): ?array
    {
        $flowJson = $flow->flow_json;

        if (! is_array($flowJson) || ! isset($flowJson['screens'])) {
            return null;
        }

        $screens = collect($flowJson['screens']);
        $current = $screens->firstWhere('id', $currentScreen);

        if ($current === null) {
            return null;
        }

        $nextScreenId = $current['next_screen'] ?? null;

        if ($nextScreenId === null) {
            return null;
        }

        return $screens->firstWhere('id', $nextScreenId);
    }

    private function extractPhoneFromToken(string $flowToken): ?string
    {
        return null;
    }
}
