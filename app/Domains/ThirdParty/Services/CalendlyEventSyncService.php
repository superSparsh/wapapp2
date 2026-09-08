<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Services;

use App\Domains\ThirdParty\Models\CalendlyEvent;
use App\Domains\ThirdParty\Models\CalendlyIntegration;
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
