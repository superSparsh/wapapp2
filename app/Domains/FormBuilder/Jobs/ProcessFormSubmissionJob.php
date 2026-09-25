<?php

declare(strict_types=1);

namespace App\Domains\FormBuilder\Jobs;

use App\Domains\FormBuilder\Services\FormSubmissionService;
use App\Domains\FormBuilder\Services\FormTemplateParamsResolver;
use App\Domains\Inbox\Services\InboxConversationService;
use App\Domains\Inbox\Services\InboxOutboundService;
use App\Domains\Templates\Support\CamsTemplateIdentity;
use App\Enums\MessageStatus;
use App\Models\FormSubmission;
use App\Models\SignupForm;
use App\Models\Template;
use App\Models\WhatsappLine;
use App\Support\PhoneNormalizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

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

    public function handle(
        FormSubmissionService $submissionService,
        InboxConversationService $conversationService,
        InboxOutboundService $outboundService,
        FormTemplateParamsResolver $paramsResolver,
    ): void {
        $submission = FormSubmission::query()->with('contact')->find($this->submissionId);

        if (! $submission instanceof FormSubmission) {
            return;
        }

        if ($submission->message_status !== 'pending') {
            return;
        }

        $form = $submission->signupForm;
        if (! $form instanceof SignupForm) {
            return;
        }

        $template = $form->template;
        if (! $template instanceof Template) {
            Log::warning('Form submission skipped: template not found', [
                'submission_id' => $submission->id,
                'form_id' => $form->id,
            ]);

            $submissionService->updateMessageStatus($submission, 'failed', null, 'Template not configured');

            return;
        }

        $templateCode = $template->whatsappCode()
            ?? CamsTemplateIdentity::code(
                $template->code,
                is_array($template->payload) ? ($template->payload['legacy_template_code'] ?? null) : null,
            );

        if ($templateCode === null || ! CamsTemplateIdentity::isProviderCode($templateCode)) {
            $submissionService->updateMessageStatus(
                $submission,
                'failed',
                null,
                'Template is not approved on WhatsApp yet.',
            );

            return;
        }

        $whatsappLine = $form->whatsappLine
            ?? WhatsappLine::query()->where('is_default', true)->first()
            ?? WhatsappLine::query()->orderBy('id')->first();

        if (! $whatsappLine instanceof WhatsappLine) {
            $submissionService->updateMessageStatus($submission, 'failed', null, 'WhatsApp line not configured');

            return;
        }

        $phone = PhoneNormalizer::normalize((string) $submission->phone);
        if ($phone === null || $phone === '') {
            $submissionService->updateMessageStatus($submission, 'failed', null, 'No phone number provided');

            return;
        }

        $templateParams = $paramsResolver->forSubmission(
            $template,
            $submission->submission_data ?? [],
            $submission->contact,
            $phone,
        );

        try {
            $conversation = $conversationService->findOrCreateConversation(
                $whatsappLine,
                $phone,
                $submission->contact?->name,
            );

            $message = $outboundService->sendTemplate(
                conversation: $conversation,
                templateCode: $templateCode,
                templateParams: $templateParams,
                language: CamsTemplateIdentity::language($template->language),
                sendImmediately: true,
                extraMetadata: [
                    'wallet_source' => 'form_builder',
                    'billable' => true,
                    'signup_form_id' => (int) $form->id,
                    'form_submission_id' => (int) $submission->id,
                    'template_category' => strtoupper((string) ($template->category ?? 'MARKETING')),
                    'contact_phone' => $phone,
                ],
            );

            $message->refresh();

            if ($message->status === MessageStatus::Failed) {
                $reason = (string) ($message->failed_reason ?: 'WhatsApp provider rejected the template.');
                $submissionService->updateMessageStatus($submission, 'failed', null, $reason);

                return;
            }

            $externalId = trim((string) ($message->external_message_id ?? ''));
            $submissionService->updateMessageStatus(
                $submission,
                'sent',
                $externalId !== '' ? $externalId : (string) $message->id,
            );
        } catch (Throwable $e) {
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
}
