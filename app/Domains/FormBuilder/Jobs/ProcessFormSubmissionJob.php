<?php

declare(strict_types=1);

namespace App\Domains\FormBuilder\Jobs;

use App\Domains\FormBuilder\Services\FormSubmissionService;
use App\Models\FormSubmission;
use App\Models\SignupForm;
use App\Models\Template;
use App\Models\WhatsappLine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessFormSubmissionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public int $submissionId,
    ) {}

    public function handle(FormSubmissionService $submissionService): void
    {
        $submission = FormSubmission::query()->find($this->submissionId);

        if (! $submission instanceof FormSubmission) {
            return;
        }

        // Already processed
        if ($submission->message_status !== 'pending') {
            return;
        }

        $form = $submission->signupForm;
        if (! $form instanceof SignupForm) {
            return;
        }

        $template = $form->template;
        if (! $template instanceof Template || ! $template->code) {
            Log::warning('Form submission skipped: template not found or no code', [
                'submission_id' => $submission->id,
                'form_id' => $form->id,
            ]);

            $submissionService->updateMessageStatus($submission, 'failed', null, 'Template not configured');

            return;
        }

        $whatsappLine = $form->whatsappLine;
        if (! $whatsappLine instanceof WhatsappLine) {
            $submissionService->updateMessageStatus($submission, 'failed', null, 'WhatsApp line not configured');

            return;
        }

        $phone = $submission->phone;
        if (! $phone) {
            $submissionService->updateMessageStatus($submission, 'failed', null, 'No phone number provided');

            return;
        }

        // Build template variables from submission data
        $components = $this->buildTemplateComponents($template, $submission->submission_data ?? []);

        try {
            $response = $this->sendTemplateMessage($whatsappLine, $phone, $template, $components);

            if ($response->successful()) {
                $messageId = $response->json('messages.0.id');

                $submissionService->updateMessageStatus(
                    $submission,
                    'sent',
                    $messageId
                );
            } else {
                $reason = $response->body();

                $submissionService->updateMessageStatus(
                    $submission,
                    'failed',
                    null,
                    $reason
                );

                Log::error('Form submission WhatsApp send failed', [
                    'submission_id' => $submission->id,
                    'status' => $response->status(),
                    'response' => $reason,
                ]);
            }
        } catch (\Exception $e) {
            $submissionService->updateMessageStatus(
                $submission,
                'failed',
                null,
                $e->getMessage()
            );

            Log::error('Form submission WhatsApp send exception', [
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build WhatsApp template components from submission data.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private function buildTemplateComponents(Template $template, array $data): array
    {
        $bodyText = $template->body_preview ?? '';
        $payload = $template->wizardPayload();

        // Extract variable placeholders from body text
        preg_match_all('/\{\{([a-zA-Z0-9_]+)\}\}/', $bodyText, $matches);
        $variableNames = $matches[1] ?? [];

        $parameters = [];
        foreach ($variableNames as $name) {
            $parameters[] = [
                'type' => 'text',
                'text' => (string) ($data[$name] ?? $data['field_'.$name] ?? ''),
            ];
        }

        $components = [];

        if (! empty($parameters)) {
            $components[] = [
                'type' => 'body',
                'parameters' => $parameters,
            ];
        }

        // Add header parameters if template has text header
        $headerType = $payload['header']['type'] ?? 'none';
        if ($headerType === 'text' && ! empty($payload['header']['text'])) {
            $headerText = $payload['header']['text'];
            preg_match_all('/\{\{([a-zA-Z0-9_]+)\}\}/', $headerText, $headerMatches);
            $headerVarNames = $headerMatches[1] ?? [];

            $headerParams = [];
            foreach ($headerVarNames as $name) {
                $headerParams[] = [
                    'type' => 'text',
                    'text' => (string) ($data[$name] ?? $data['field_'.$name] ?? ''),
                ];
            }

            if (! empty($headerParams)) {
                $components[] = [
                    'type' => 'header',
                    'parameters' => $headerParams,
                ];
            }
        }

        return $components;
    }

    /**
     * Send the template message via WhatsApp API.
     *
     * @param  array<int, array<string, mixed>>  $components
     */
    private function sendTemplateMessage(WhatsappLine $line, string $phone, Template $template, array $components): \Illuminate\Http\Client\Response
    {
        $apiBase = config('whatsapp.api_base_url', 'https://dmp.alibabacms.com');
        $token = $line->metadata['access_token'] ?? null;

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => ltrim($phone, '+'),
            'type' => 'template',
            'template' => [
                'name' => $template->code,
                'language' => ['code' => $template->language],
                'components' => $components,
            ],
        ];

        return Http::withToken($token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post("{$apiBase}/v1/messages", $payload);
    }
}
