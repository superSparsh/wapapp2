<?php

declare(strict_types=1);

namespace App\Domains\FormBuilder\Services;

use App\Domains\Audience\Enums\ContactStatus;
use App\Domains\Audience\Models\Blacklist;
use App\Domains\Drip\Services\DripTriggerDispatcher;
use App\Domains\FormBuilder\Enums\FieldType;
use App\Domains\FormBuilder\Jobs\ProcessFormSubmissionJob;
use App\Enums\ContactOptInStatus;
use App\Models\Contact;
use App\Models\FormSubmission;
use App\Models\SignupForm;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\DB;

class FormSubmissionService
{
    public function __construct(
        private readonly DripTriggerDispatcher $dripTriggerDispatcher,
    ) {}

    /**
     * Process a public form submission.
     *
     * 1. Create/update contact with submitted phone number (same format as list subscribers)
     * 2. Store the submission record
     * 3. Increment the form's cached submission_count
     * 4. Dispatch async job to send WhatsApp template message
     *
     * @param  array<string, mixed>  $data  Submitted form data (field values keyed by field type)
     */
    public function process(SignupForm $form, array $data): FormSubmission
    {
        $phone = $this->extractPhone($form, $data);

        return DB::transaction(function () use ($form, $data, $phone): FormSubmission {
            $contact = $this->upsertContact($phone, $data, $form);

            $submission = FormSubmission::query()->create([
                'signup_form_id' => $form->id,
                'contact_id' => $contact?->id,
                'template_id' => $form->template_id,
                'phone' => $phone,
                'submission_data' => $data,
                'message_status' => 'pending',
            ]);

            $form->increment('submission_count');

            if ($form->template_id && $phone) {
                ProcessFormSubmissionJob::dispatch($submission->id);
            }

            return $submission->refresh();
        });
    }

    /**
     * Update submission status when WhatsApp message callback arrives.
     *
     * @param  string|null  $externalId  Provider MessageId (legacy conversations.msg_id)
     * @param  int|null  $outboundMessageId  Local messages.id link for webhook matching
     */
    public function updateMessageStatus(
        FormSubmission $submission,
        string $status,
        ?string $externalId = null,
        ?string $failedReason = null,
        ?int $outboundMessageId = null,
    ): void {
        $status = strtolower(trim($status));
        $current = strtolower((string) ($submission->message_status ?? 'pending'));

        if (! $this->shouldApplyStatus($current, $status)) {
            $patch = [];
            if ($externalId && blank($submission->external_message_id)) {
                $patch['external_message_id'] = $externalId;
            }
            if ($outboundMessageId && blank($submission->outbound_message_id)) {
                $patch['outbound_message_id'] = $outboundMessageId;
            }
            if ($patch !== []) {
                $submission->update($patch);
            }

            return;
        }

        $updates = ['message_status' => $status];

        if ($externalId) {
            $updates['external_message_id'] = $externalId;
        }

        if ($outboundMessageId) {
            $updates['outbound_message_id'] = $outboundMessageId;
        }

        $now = now();

        if (in_array($status, ['sent', 'delivered', 'read'], true)) {
            $updates['sent_at'] = $submission->sent_at ?? $now;
        }

        if (in_array($status, ['delivered', 'read'], true)) {
            $updates['delivered_at'] = $submission->delivered_at ?? $now;
        }

        if ($status === 'read') {
            $updates['read_at'] = $submission->read_at ?? $now;
        }

        if ($status === 'failed') {
            $updates['failed_at'] = $submission->failed_at ?? $now;
            $updates['failed_reason'] = $failedReason;
        }

        $submission->update($updates);

        $this->syncFormStats($submission);
    }

    private function shouldApplyStatus(string $current, string $next): bool
    {
        if ($current === $next) {
            return true;
        }

        $order = [
            'pending' => 0,
            'sent' => 1,
            'delivered' => 2,
            'read' => 3,
        ];

        if ($next === 'failed') {
            // Don't overwrite a successful delivery/read with a late failure noise.
            return ! in_array($current, ['delivered', 'read'], true);
        }

        if (! array_key_exists($next, $order) || ! array_key_exists($current, $order)) {
            return true;
        }

        return $order[$next] >= $order[$current];
    }

    /**
     * Extract the phone number from submitted data.
     *
     * @param  array<string, mixed>  $data
     */
    private function extractPhone(SignupForm $form, array $data): ?string
    {
        $fields = $form->fields ?? [];

        foreach ($fields as $field) {
            $type = FieldType::tryFrom((string) ($field['type'] ?? ''));
            $name = strtolower((string) ($field['name'] ?? $field['id'] ?? ''));
            $isPhone = $type === FieldType::Phone
                || in_array($name, ['phone', 'phone_number', 'whatsapp_number', 'whatsapp_phone_number'], true);

            if (! $isPhone) {
                continue;
            }

            $keys = array_values(array_filter([
                'phone',
                'phone_number',
                'whatsapp_number',
                isset($field['id']) ? 'field_'.$field['id'] : null,
                $type ? 'field_'.$type->value : null,
                $name !== '' ? $name : null,
            ]));

            foreach ($keys as $key) {
                if (isset($data[$key]) && filled($data[$key])) {
                    return PhoneNormalizer::normalize((string) $data[$key]);
                }
            }
        }

        foreach (['phone', 'phone_number', 'whatsapp_number'] as $key) {
            if (isset($data[$key]) && filled($data[$key])) {
                return PhoneNormalizer::normalize((string) $data[$key]);
            }
        }

        return null;
    }

    /**
     * Create or update a contact using the same shape as list subscriber saves.
     *
     * @param  array<string, mixed>  $data
     */
    private function upsertContact(?string $phone, array $data, SignupForm $form): ?Contact
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        $firstName = $this->extractFieldValue($data, 'first_name');
        $lastName = $this->extractFieldValue($data, 'last_name');
        $name = trim(($firstName ?? '').' '.($lastName ?? ''))
            ?: $this->extractFieldValue($data, 'name')
            ?? $this->extractFieldValue($data, 'header');
        $email = $this->extractFieldValue($data, 'email');

        if (Blacklist::isBlacklisted($phone, $email)) {
            return null;
        }

        $countryCode = null;
        if (str_starts_with($phone, '91') && strlen($phone) >= 12) {
            $countryCode = '91';
        }

        $customFields = array_filter([
            'FIRST_NAME' => $firstName,
            'LAST_NAME' => $lastName,
        ], static fn ($value) => filled($value));

        $payload = array_filter([
            'name' => $name,
            'email' => $email,
            'country_code' => $countryCode,
            'status' => ContactStatus::Subscribed,
            'opt_in_status' => ContactOptInStatus::OptedIn,
            'opted_in_at' => now(),
            'source' => 'signup_form',
            'mail_list_id' => $form->list_id,
            'send_opt_in_message' => 'no',
            'custom_fields' => $customFields !== [] ? $customFields : null,
        ], static fn ($value) => $value !== null && $value !== '');

        $listId = $form->list_id ? (int) $form->list_id : null;

        $trashed = $listId !== null
            ? Contact::onlyTrashed()
                ->where('phone', $phone)
                ->where('mail_list_id', $listId)
                ->first()
            : null;

        if ($trashed !== null) {
            $trashed->restore();
            $trashed->update($payload);

            return $trashed->fresh();
        }

        $lookup = ['phone' => $phone];
        if ($listId !== null) {
            $lookup['mail_list_id'] = $listId;
        }

        $contact = Contact::query()->updateOrCreate($lookup, $payload);

        if ($contact->wasRecentlyCreated) {
            $this->dripTriggerDispatcher->dispatchForContact('welcome-new-subscriber', $contact);
        }

        return $contact->fresh();
    }

    /**
     * Extract a field value from submission data by field type or name.
     *
     * @param  array<string, mixed>  $data
     */
    private function extractFieldValue(array $data, string $key): ?string
    {
        if (isset($data[$key]) && filled($data[$key])) {
            return (string) $data[$key];
        }

        $prefixed = 'field_'.$key;
        if (isset($data[$prefixed]) && filled($data[$prefixed])) {
            return (string) $data[$prefixed];
        }

        foreach ($data as $k => $v) {
            if (str_contains(strtolower((string) $k), $key) && filled($v)) {
                return (string) $v;
            }
        }

        return null;
    }

    private function syncFormStats(FormSubmission $submission): void
    {
        $form = $submission->signupForm;
        if (! $form) {
            return;
        }

        $counts = FormSubmission::query()
            ->where('signup_form_id', $form->id)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN sent_at IS NOT NULL THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN read_at IS NOT NULL THEN 1 ELSE 0 END) as read_count,
                SUM(CASE WHEN delivered_at IS NOT NULL THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN message_status = 'failed' OR (failed_at IS NOT NULL AND delivered_at IS NULL) THEN 1 ELSE 0 END) as failed
            ")
            ->first();

        if ($counts) {
            $form->update([
                'submission_count' => (int) $counts->total,
                'sent_count' => (int) $counts->sent,
                'read_count' => (int) $counts->read_count,
                'delivered_count' => (int) $counts->delivered,
                'failed_count' => (int) $counts->failed,
            ]);
        }
    }
}
