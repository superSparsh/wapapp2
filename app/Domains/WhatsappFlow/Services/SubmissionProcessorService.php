<?php

declare(strict_types=1);

namespace App\Domains\WhatsappFlow\Services;

use App\Enums\WhatsappFlowSubmitAction;
use App\Models\Contact;
use App\Models\WhatsappFlow;
use App\Models\WhatsappFlowSubmission;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SubmissionProcessorService
{
    /**
     * Process a submission based on the flow's on_submit_action.
     */
    public function processSubmission(WhatsappFlowSubmission $submission, WhatsappFlow $flow): void
    {
        $action = $flow->on_submit_action;

        if ($action === null) {
            $submission->markProcessed();

            return;
        }

        match ($action) {
            WhatsappFlowSubmitAction::CreateLead => $this->createLead($submission, $flow),
            WhatsappFlowSubmitAction::UpdateContact => $this->updateContact($submission, $flow),
            WhatsappFlowSubmitAction::Webhook => $this->sendWebhook($submission, $flow),
            WhatsappFlowSubmitAction::None => null,
        };

        $submission->markProcessed();
    }

    private function createLead(WhatsappFlowSubmission $submission, WhatsappFlow $flow): void
    {
        $data = $submission->form_data ?? [];

        // Find or create contact by phone
        $contact = Contact::query()->firstOrCreate(
            ['phone' => $submission->contact_phone],
            [
                'name' => $data['full_name'] ?? $data['name'] ?? 'Unknown',
                'email' => $data['email'] ?? null,
                'source' => 'whatsapp_flow',
            ]
        );

        // Update contact with form data if they already existed
        if (! $contact->wasRecentlyCreated) {
            $updates = array_filter([
                'name' => $data['full_name'] ?? $data['name'] ?? null,
                'email' => $data['email'] ?? null,
            ]);

            if ($updates !== []) {
                $contact->update($updates);
            }
        }

        Log::info('WhatsApp Flow: Lead created from submission', [
            'submission_id' => $submission->id,
            'contact_id' => $contact->id,
            'flow_id' => $flow->id,
        ]);
    }

    private function updateContact(WhatsappFlowSubmission $submission, WhatsappFlow $flow): void
    {
        $contact = Contact::query()
            ->where('phone', $submission->contact_phone)
            ->first();

        if ($contact === null) {
            Log::warning('WhatsApp Flow: Contact not found for update', [
                'phone' => $submission->contact_phone,
                'flow_id' => $flow->id,
            ]);

            return;
        }

        $data = $submission->form_data ?? [];
        $updates = array_filter([
            'name' => $data['full_name'] ?? $data['name'] ?? null,
            'email' => $data['email'] ?? null,
        ]);

        if ($updates !== []) {
            $contact->update($updates);
        }
    }

    private function sendWebhook(WhatsappFlowSubmission $submission, WhatsappFlow $flow): void
    {
        $webhookUrl = $flow->on_submit_webhook_url;

        if ($webhookUrl === null || $webhookUrl === '') {
            return;
        }

        try {
            Http::timeout(10)->post($webhookUrl, [
                'event' => 'whatsapp_flow_submission',
                'flow_id' => $flow->uuid,
                'flow_name' => $flow->name,
                'phone' => $submission->contact_phone,
                'data' => $submission->form_data,
                'submitted_at' => $submission->created_at->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::error('WhatsApp Flow: Webhook delivery failed', [
                'submission_id' => $submission->id,
                'webhook_url' => $webhookUrl,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
