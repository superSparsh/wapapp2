<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Services;

use App\Domains\Alerts\Services\AlertDispatcher;
use App\Domains\ThirdParty\Enums\MessageLogStatus;
use App\Domains\ThirdParty\Models\CalendlyEvent;
use App\Domains\ThirdParty\Models\CalendlyIntegration;
use App\Domains\ThirdParty\Models\CalendlyMessageLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Handles syncing Calendly events from the Calendly API into the local DB.
 * Extracted from SyncCalendlyEventsJob for testability and single-responsibility.
 */
class CalendlyEventSyncService
{
    /**
     * Sync all events for the integration from the Calendly API.
     * Handles pagination, invitee data, phone extraction, and upserts.
     */
    public function sync(CalendlyIntegration $integration): void
    {
        $token   = $integration->settings['access_token'] ?? null;
        $userUri = $integration->settings['user_uri'] ?? null;

        if (! $token || ! $userUri) {
            return;
        }

        $tz  = 'UTC';
        $min = Carbon::now($tz)->subMonths(3)->startOfDay()->toIso8601String();
        $max = Carbon::now($tz)->addMonths(3)->endOfDay()->toIso8601String();

        $params = [
            'user'           => $userUri,
            'sort'           => 'start_time:asc',
            'count'          => 100,
            'min_start_time' => $min,
            'max_start_time' => $max,
        ];

        $response = Http::withToken($token)->timeout(30)->get('https://api.calendly.com/scheduled_events', $params);

        if (! $response->successful()) {
            Log::warning('CalendlyEventSyncService: failed to fetch events', [
                'status' => $response->status(),
                'user_id' => $integration->user_id,
            ]);
            return;
        }

        do {
            $events   = $response->json('collection') ?? [];
            $nextPage = null;

            foreach ($events as $event) {
                $this->processEvent($integration, $event, $token);
            }

            $pagination = $response->json('pagination') ?? [];
            $nextPage   = $pagination['next_page'] ?? null;

            if ($nextPage) {
                $response = Http::withToken($token)->timeout(30)->get($nextPage);
            }
        } while ($nextPage && $response->successful());

        // Mark first sync time so WhatsApp notifications only fire for future events
        if ($integration->first_synced_at === null) {
            $integration->first_synced_at = now();
            $integration->save();
        }
    }

    /**
     * Process a single raw Calendly event array: fetch invitee, resolve phone, upsert.
     *
     * @param array<string, mixed> $event
     */
    private function processEvent(CalendlyIntegration $integration, array $event, string $token): void
    {
        $invitees = $this->fetchInvitees($event, $token);
        $invitee  = $invitees[0] ?? null;

        $inviteeEmail = null;
        $formResponses = null;
        $whatsapp = null;

        if ($invitee !== null) {
            $inviteeEmail  = $invitee['email'] ?? $invitee['user_email'] ?? null;
            $formResponses = $invitee;
            $whatsapp      = $this->resolvePhone($invitee);
        }

        $payload = [
            'user_id'    => $integration->user_id,
            'status'     => $event['status'] ?? 'active',
            'event_type' => $event['event_type'] ?? null,
            'start_time' => Carbon::parse($event['start_time']),
            'end_time'   => Carbon::parse($event['end_time']),
            'event_uri'  => $event['uri'],
            'raw_payload' => $event,
        ];

        if ($inviteeEmail !== null) {
            $payload['invitee_email'] = $inviteeEmail;
        }
        if ($formResponses !== null) {
            $payload['form_responses'] = $formResponses;
        }
        if ($whatsapp !== null && $whatsapp !== '') {
            $payload['whatsapp_number'] = $whatsapp;
        }

        CalendlyEvent::query()->updateOrCreate(
            ['event_id' => $event['uri']],
            $payload
        );

        $stored = CalendlyEvent::query()->where('event_id', $event['uri'])->first();
        if ($stored === null) {
            return;
        }

        $enableWhatsapp = (bool) ($integration->settings['enable_whatsapp'] ?? false);
        if (! $enableWhatsapp) {
            return;
        }

        $status = strtolower((string) ($event['status'] ?? 'active'));
        $eventName = (string) ($stored->raw_payload['name'] ?? $stored->event_type ?? 'Meeting');

        try {
            if ($status === 'canceled' && ! $stored->notified_canceled) {
                $this->notifyAndLog($integration, $stored, 'canceled', 'operational-alerts.calendly.customer_canceled', 'operational-alerts.calendly.admin_canceled', $eventName);
                $stored->update(['notified_canceled' => true]);
            } elseif ($status !== 'canceled' && ! $stored->notified_created && $integration->first_synced_at !== null) {
                $this->notifyAndLog($integration, $stored, 'created', 'operational-alerts.calendly.customer_created', 'operational-alerts.calendly.admin_created', $eventName);
                $stored->update(['notified_created' => true]);
            }
        } catch (\Throwable $e) {
            Log::warning('Calendly booking alert failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Legacy CAMS template variables (SendsCalendlyWhatsapp).
     * Missing links must be "N/A" — empty/null values cause InvalidParameter.
     *
     * @return array<string, string>
     */
    public function templateParamsForEvent(
        CalendlyEvent $event,
        ?string $eventName = null,
        bool $forAdmin = false,
    ): array {
        $tz = (string) (tenant('timezone') ?: config('app.timezone', 'UTC'));
        $eventName = $this->fallbackNa(
            $eventName ?? (string) ($event->raw_payload['name'] ?? $event->event_type ?? 'Meeting'),
            'Meeting',
        );

        $joinLink = $this->fallbackNa(
            (string) ($event->raw_payload['location']['join_url'] ?? ''),
        );

        $form = is_array($event->form_responses) ? $event->form_responses : [];
        $rescheduleLink = $this->extractCalendlyUuid($form['reschedule_url'] ?? null);
        $cancelLink = $this->extractCalendlyUuid($form['cancel_url'] ?? null);

        $params = [
            'full_name' => $this->fallbackNa($this->resolveInviteeName($event), 'Guest'),
            'calendly_event_name' => $eventName,
            'calendly_event_start_time' => $this->formatEventTime($event->start_time, $tz),
            'calendly_event_end_time' => $this->formatEventTime($event->end_time, $tz),
            'calendly_event_join_link' => $joinLink,
            'reschedule_link' => $rescheduleLink,
            'cancel_link' => $cancelLink,
        ];

        if ($forAdmin) {
            $params['whatsapp_number'] = $this->fallbackNa((string) ($event->whatsapp_number ?? ''));
            $params['invitee_email'] = $this->fallbackNa((string) ($event->invitee_email ?? ''));
        }

        return $params;
    }

    public function templateKeyFor(string $recipientType, string $eventType): ?string
    {
        $recipientType = strtolower(trim($recipientType));
        $eventType = strtolower(trim($eventType));

        return match (true) {
            $recipientType === 'customer' && $eventType === 'created' => 'operational-alerts.calendly.customer_created',
            $recipientType === 'customer' && $eventType === 'canceled' => 'operational-alerts.calendly.customer_canceled',
            $recipientType === 'customer' && $eventType === 'reminder' => 'operational-alerts.calendly.customer_reminder',
            $recipientType === 'admin' && $eventType === 'created' => 'operational-alerts.calendly.admin_created',
            $recipientType === 'admin' && $eventType === 'canceled' => 'operational-alerts.calendly.admin_canceled',
            $recipientType === 'admin' && $eventType === 'reminder' => 'operational-alerts.calendly.admin_reminder',
            default => null,
        };
    }

    private function resolveInviteeName(CalendlyEvent $event): string
    {
        $form = is_array($event->form_responses) ? $event->form_responses : [];
        $candidates = [
            $form['name'] ?? null,
            $form['first_name'] ?? null,
            is_array($form['name'] ?? null) ? ($form['name']['formatted_name'] ?? null) : null,
        ];

        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        $email = trim((string) ($event->invitee_email ?? ''));

        return $email !== '' ? $email : 'Guest';
    }

    /**
     * Send customer + admin WhatsApp notifications and write message logs (sent / failed / skipped).
     */
    private function notifyAndLog(
        CalendlyIntegration $integration,
        CalendlyEvent $event,
        string $eventType,
        string $customerTemplateKey,
        string $adminTemplateKey,
        string $eventName,
    ): void {
        $dispatcher = app(AlertDispatcher::class);
        $phone = (string) ($event->whatsapp_number ?? '');
        $customerParams = $this->templateParamsForEvent($event, $eventName, forAdmin: false);

        if ($phone === '') {
            $this->writeMessageLog($event, 'customer', null, $eventName, $eventType, MessageLogStatus::Skipped, 'No WhatsApp number provided by invitee. Add a "WhatsApp number" or "Phone number" question to your Calendly event type so customers receive meeting notifications.');
        } else {
            $digits = preg_replace('/\D/', '', $phone) ?: '';
            if (strlen($digits) < 10) {
                $this->writeMessageLog($event, 'customer', $digits, $eventName, $eventType, MessageLogStatus::Skipped, 'Phone number from form has fewer than 10 digits.');
            } else {
                $result = $dispatcher->calendarWhatsApp($customerTemplateKey, $phone, $customerParams);
                $this->writeMessageLog(
                    $event,
                    'customer',
                    $digits,
                    $eventName,
                    $eventType,
                    $result['ok'] ? MessageLogStatus::Sent : MessageLogStatus::Failed,
                    $result['ok'] ? null : ($result['error'] ?: 'WhatsApp API did not return success.'),
                );
            }
        }

        $adminPhone = (string) ($integration->settings['whatsapp_number'] ?? '');
        if ($adminPhone === '') {
            return;
        }

        $adminDigits = preg_replace('/\D/', '', $adminPhone) ?: '';
        if (strlen($adminDigits) < 10) {
            $this->writeMessageLog($event, 'admin', $adminDigits, $eventName, $eventType, MessageLogStatus::Skipped, 'Admin WhatsApp number has fewer than 10 digits.');

            return;
        }

        $adminParams = $this->templateParamsForEvent($event, $eventName, forAdmin: true);
        $result = $dispatcher->calendarWhatsApp($adminTemplateKey, $adminPhone, $adminParams);
        $this->writeMessageLog(
            $event,
            'admin',
            $adminDigits,
            $eventName,
            $eventType,
            $result['ok'] ? MessageLogStatus::Sent : MessageLogStatus::Failed,
            $result['ok'] ? null : ($result['error'] ?: 'WhatsApp API did not return success.'),
        );
    }

    private function formatEventTime(mixed $time, string $timezone): string
    {
        if (! $time instanceof \DateTimeInterface) {
            return 'N/A';
        }

        try {
            return Carbon::instance($time)->timezone($timezone)->format('d M Y, h:i A');
        } catch (\Throwable) {
            return Carbon::instance($time)->format('d M Y, h:i A');
        }
    }

    private function extractCalendlyUuid(mixed $url): string
    {
        $url = trim((string) $url);
        if ($url === '' || strcasecmp($url, 'N/A') === 0) {
            return 'N/A';
        }

        if (preg_match('/\/([a-f0-9-]+)$/i', $url, $matches) === 1) {
            return $matches[1];
        }

        return 'N/A';
    }

    private function fallbackNa(string $value, string $fallback = 'N/A'): string
    {
        $value = trim($value);

        return $value !== '' ? $value : $fallback;
    }

    private function writeMessageLog(
        CalendlyEvent $event,
        string $recipientType,
        ?string $recipientNumber,
        string $eventName,
        string $eventType,
        MessageLogStatus $status,
        ?string $errorMessage,
    ): void {
        CalendlyMessageLog::query()->create([
            'user_id'          => $event->user_id,
            'event_id'         => $event->id,
            'recipient_type'   => $recipientType,
            'recipient_number' => $recipientNumber,
            'invitee_email'    => $event->invitee_email,
            'event_name'       => $eventName,
            'event_type'       => $eventType,
            'status'           => $status,
            'error_message'    => $errorMessage,
            'sent_at'          => now(),
        ]);
    }

    /**
     * Fetch invitees for an event. Retries once if the event has invitees but the API returns empty.
     *
     * @param array<string, mixed> $event
     * @return array<int, array<string, mixed>>
     */
    private function fetchInvitees(array $event, string $token): array
    {
        $resp     = Http::withToken($token)->timeout(15)->get($event['uri'] . '/invitees', ['count' => 100]);
        $invitees = $resp->successful() ? ($resp->json('collection') ?? []) : [];

        // Retry once if Calendly reports invitees but API returned none (race condition)
        $expected = (int) ($event['invitees_counter']['total'] ?? 0);
        if ($expected > 0 && count($invitees) === 0) {
            sleep(2);
            $resp2    = Http::withToken($token)->timeout(15)->get($event['uri'] . '/invitees', ['count' => 100]);
            $invitees = $resp2->successful() ? ($resp2->json('collection') ?? []) : [];
        }

        return $invitees;
    }

    /**
     * Extract phone number from invitee data using a multi-fallback strategy.
     *
     * @param array<string, mixed> $invitee
     */
    private function resolvePhone(array $invitee): ?string
    {
        // 1. Dedicated phone/SMS fields
        $phone = $invitee['phone_number'] ?? $invitee['sms_reminder_number'] ?? null;
        if ($phone) {
            return $this->normalizePhone($phone);
        }

        $answers = collect((array) ($invitee['questions_and_answers'] ?? []));

        // 2. Exact "Phone" label match (Calendly Phone Number field)
        $phone = $this->answerForQuestion($answers, fn ($q) => $q === 'phone');
        if ($phone) {
            return $this->normalizePhone($phone);
        }

        // 3. Keywords: whatsapp, phone number, mobile, etc.
        $phone = $this->answerForQuestion($answers, function (string $q): bool {
            foreach (['whatsapp', 'phone number', 'contact number', 'your number', 'phone', 'mobile', 'telephone'] as $kw) {
                if (str_contains($q, $kw)) {
                    return true;
                }
            }
            return false;
        });
        if ($phone) {
            return $this->normalizePhone($phone);
        }

        // 4. Any answer that contains ≥10 digits (last resort)
        $found = $answers->first(function (array $a): bool {
            return strlen(preg_replace('/\D/', '', (string) ($a['answer'] ?? '')) ?: '') >= 10;
        });

        return $found ? $this->normalizePhone((string) ($found['answer'] ?? '')) : null;
    }

    /**
     * @param \Closure(string): bool $matcher
     */
    private function answerForQuestion(Collection $answers, \Closure $matcher): ?string
    {
        $hit = $answers->first(function (array $a) use ($matcher): bool {
            return $matcher(strtolower(trim((string) ($a['question'] ?? ''))));
        });

        return $hit ? (string) ($hit['answer'] ?? '') : null;
    }

    private function normalizePhone(string $phone): ?string
    {
        $clean = preg_replace('/\s+/', '', $phone);
        return strlen(preg_replace('/\D/', '', $clean) ?: '') >= 10 ? $clean : null;
    }
}
