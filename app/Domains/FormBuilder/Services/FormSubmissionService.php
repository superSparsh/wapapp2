<?php

declare(strict_types=1);

namespace App\Domains\FormBuilder\Services;

use App\Domains\FormBuilder\Enums\FieldType;
use App\Domains\FormBuilder\Jobs\ProcessFormSubmissionJob;
use App\Models\Contact;
use App\Models\FormSubmission;
use App\Models\SignupForm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FormSubmissionService
{
    /**
     * Process a public form submission.
     *
     * 1. Create/update contact with submitted phone number
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
            // 1. Create or update contact
            $contact = $this->upsertContact($phone, $data, $form);

            // 2. Store the submission record
            $submission = FormSubmission::query()->create([
                'signup_form_id' => $form->id,
                'contact_id' => $contact?->id,
                'template_id' => $form->template_id,
                'phone' => $phone,
                'submission_data' => $data,
                'message_status' => 'pending',
            ]);

            // 3. Increment cached submission count on the form
            $form->increment('submission_count');

            // 4. Dispatch async job for WhatsApp message
            if ($form->template_id && $phone) {
                ProcessFormSubmissionJob::dispatch($submission->id);
            }

            return $submission->refresh();
        });
    }

    /**
     * Update submission status when WhatsApp message callback arrives.
     */
    public function updateMessageStatus(FormSubmission $submission, string $status, ?string $externalId = null, ?string $failedReason = null): void
    {
        $updates = ['message_status' => $status];

        if ($externalId) {
            $updates['external_message_id'] = $externalId;
        }

        if ($status === 'sent') {
            $updates['sent_at'] = now();
        } elseif ($status === 'delivered') {
            $updates['delivered_at'] = now();
        } elseif ($status === 'read') {
            $updates['read_at'] = now();
        } elseif ($status === 'failed') {
            $updates['failed_at'] = now();
            $updates['failed_reason'] = $failedReason;
        }

        $submission->update($updates);

        // Sync cached counts on the form (atomic increment)
        $this->syncFormStats($submission);
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
                    return $this->normalizePhone((string) $data[$key]);
                }
            }
        }

        // Fallback: look for common phone keys in data
        foreach (['phone', 'phone_number', 'whatsapp_number'] as $key) {
            if (isset($data[$key]) && filled($data[$key])) {
                return $this->normalizePhone((string) $data[$key]);
            }
        }

        return null;
    }

    /**
     * Normalize phone number to E.164-ish format.
     */
    private function normalizePhone(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9+]/', '', $phone);

        if (! str_starts_with($cleaned, '+')) {
            $cleaned = '+'.$cleaned;
        }

        return $cleaned;
    }

    /**
     * Create or update a contact with the submitted data.
     */
    private function upsertContact(string $phone, array $data, SignupForm $form): ?Contact
    {
        if (! $phone) {
            return null;
        }

        // Build name from first_name + last_name if available, fallback to name/header
        $firstName = $this->extractFieldValue($data, 'first_name');
        $lastName = $this->extractFieldValue($data, 'last_name');
        $name = trim(($firstName ?? '').' '.($lastName ?? '')) ?: $this->extractFieldValue($data, 'name') ?? $this->extractFieldValue($data, 'header');
        $email = $this->extractFieldValue($data, 'email');

        return Contact::query()->updateOrCreate(
            ['phone' => $phone],
            array_filter([
                'name' => $name,
                'email' => $email,
                'source' => 'signup_form',
                'mail_list_id' => $form->list_id,
                'opted_in_at' => now(),
            ], fn ($value) => filled($value))
        );
    }

    /**
     * Extract a field value from submission data by field type or name.
     *
     * @param  array<string, mixed>  $data
     */
    private function extractFieldValue(array $data, string $key): ?string
    {
        // Try direct key
        if (isset($data[$key]) && filled($data[$key])) {
            return (string) $data[$key];
        }

        // Try field-prefixed keys
        $prefixed = 'field_'.$key;
        if (isset($data[$prefixed]) && filled($data[$prefixed])) {
            return (string) $data[$prefixed];
        }

        // Try nested keys
        foreach ($data as $k => $v) {
            if (str_contains(strtolower($k), $key) && filled($v)) {
                return (string) $v;
            }
        }

        return null;
    }

    /**
     * Sync cached statistics on the form based on submission status changes.
     */
    private function syncFormStats(FormSubmission $submission): void
    {
        $form = $submission->signupForm;
        if (! $form) {
            return;
        }

        // Recalculate from submissions table (atomic, accurate)
        $counts = FormSubmission::query()
            ->where('signup_form_id', $form->id)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN message_status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN message_status = 'read' THEN 1 ELSE 0 END) as read_count,
                SUM(CASE WHEN message_status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN message_status = 'failed' THEN 1 ELSE 0 END) as failed
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
