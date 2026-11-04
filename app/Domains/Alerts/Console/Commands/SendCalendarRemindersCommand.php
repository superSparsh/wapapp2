<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Console\Commands;

use App\Domains\Alerts\Services\AlertDispatcher;
use App\Domains\ThirdParty\Enums\EventStatus;
use App\Domains\ThirdParty\Enums\MessageLogStatus;
use App\Domains\ThirdParty\Models\CalendlyEvent;
use App\Domains\ThirdParty\Models\CalendlyIntegration;
use App\Domains\ThirdParty\Models\CalendlyMessageLog;
use App\Domains\ThirdParty\Models\GoogleCalendarEvent;
use App\Domains\ThirdParty\Models\GoogleCalendarIntegration;
use App\Domains\ThirdParty\Models\GoogleCalendarMessageLog;
use App\Domains\ThirdParty\Services\CalendlyEventSyncService;
use App\Domains\ThirdParty\Services\GoogleCalendarEventSyncService;
use App\Domains\Webhooks\Services\WhatsappLineRegistryService;
use App\Support\Console\Concerns\IteratesTenants;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SendCalendarRemindersCommand extends Command
{
    use IteratesTenants;

    protected $signature = 'alerts:calendar-reminders {--tenants=* : Tenant IDs to process}';

    protected $description = 'Send Calendly / Google Calendar upcoming event WhatsApp reminders.';

    public function handle(AlertDispatcher $dispatcher): int
    {
        $sent = 0;

        $this->foreachTenant(function () use ($dispatcher, &$sent): void {
            CalendlyEvent::query()
                ->where('status', EventStatus::Active)
                ->whereNotNull('start_time')
                ->whereBetween('start_time', [now(), now()->addMinutes(35)])
                ->limit(100)
                ->get()
                ->each(function (CalendlyEvent $event) use ($dispatcher, &$sent): void {
                    $cacheKey = 'calendly-reminder:'.(tenant('id') ?? 't').':'.$event->id;
                    if (! Cache::add($cacheKey, 1, now()->addDay())) {
                        return;
                    }

                    $integration = CalendlyIntegration::query()->where('user_id', $event->user_id)->first();
                    if (! ($integration?->settings['enable_whatsapp'] ?? false)) {
                        return;
                    }

                    $eventName = (string) ($event->raw_payload['name'] ?? $event->event_type ?? 'Meeting');
                    $sent += $this->sendCalendlyReminder($dispatcher, $integration, $event, $eventName);
                });

            GoogleCalendarEvent::query()
                ->where('status', EventStatus::Active)
                ->whereNotNull('start_time')
                ->whereBetween('start_time', [now(), now()->addDay()])
                ->limit(100)
                ->get()
                ->each(function (GoogleCalendarEvent $event) use ($dispatcher, &$sent): void {
                    $integration = GoogleCalendarIntegration::query()->where('user_id', $event->user_id)->first();
                    if (! ($integration?->settings['enable_whatsapp'] ?? false)) {
                        return;
                    }

                    $minutes = $integration->reminderMinutes();
                    if ($event->start_time === null || $event->start_time->greaterThan(now()->addMinutes($minutes))) {
                        return;
                    }

                    $sentDays = (array) ($event->reminder_days_sent ?? []);
                    $dayKey = now()->toDateString();
                    if (in_array($dayKey, $sentDays, true)) {
                        return;
                    }

                    $eventName = (string) ($event->summary ?? 'Meeting');
                    $sent += $this->sendGoogleReminder($dispatcher, $integration, $event, $eventName);

                    $event->update([
                        'reminder_days_sent' => array_values(array_unique([...$sentDays, $dayKey])),
                    ]);
                });
        });

        $this->info("Calendar reminders processed ({$sent}).");

        return self::SUCCESS;
    }

    private function sendCalendlyReminder(
        AlertDispatcher $dispatcher,
        CalendlyIntegration $integration,
        CalendlyEvent $event,
        string $eventName,
    ): int {
        $sent = 0;
        $phone = (string) ($event->whatsapp_number ?? '');
        $sync = app(CalendlyEventSyncService::class);
        $customerParams = $sync->templateParamsForEvent($event, $eventName, forAdmin: false);

        if ($phone === '') {
            CalendlyMessageLog::query()->create([
                'user_id' => $event->user_id,
                'event_id' => $event->id,
                'recipient_type' => 'customer',
                'recipient_number' => null,
                'invitee_email' => $event->invitee_email,
                'event_name' => $eventName,
                'event_type' => 'reminder',
                'status' => MessageLogStatus::Skipped,
                'error_message' => 'No WhatsApp number provided by invitee.',
                'sent_at' => now(),
            ]);
        } else {
            $result = $dispatcher->calendarWhatsApp(
                'operational-alerts.calendly.customer_reminder',
                $phone,
                $customerParams,
            );
            $messageId = $this->messageIdFromResult($result);

            CalendlyMessageLog::query()->create([
                'user_id' => $event->user_id,
                'event_id' => $event->id,
                'recipient_type' => 'customer',
                'recipient_number' => preg_replace('/\D/', '', $phone) ?: null,
                'invitee_email' => $event->invitee_email,
                'event_name' => $eventName,
                'event_type' => 'reminder',
                'status' => $result['ok'] ? MessageLogStatus::Sent : MessageLogStatus::Failed,
                'external_message_id' => $messageId,
                'error_message' => $result['ok'] ? null : ($result['error'] ?: 'WhatsApp API did not return success.'),
                'sent_at' => now(),
            ]);

            if ($result['ok']) {
                $sent++;
            }
        }

        $adminPhone = (string) ($integration->settings['whatsapp_number'] ?? '');
        if ($adminPhone === '') {
            return $sent;
        }

        $adminDigits = preg_replace('/\D/', '', $adminPhone) ?: '';
        if (strlen($adminDigits) < 10) {
            CalendlyMessageLog::query()->create([
                'user_id' => $event->user_id,
                'event_id' => $event->id,
                'recipient_type' => 'admin',
                'recipient_number' => $adminDigits,
                'invitee_email' => $event->invitee_email,
                'event_name' => $eventName,
                'event_type' => 'reminder',
                'status' => MessageLogStatus::Skipped,
                'error_message' => 'Admin WhatsApp number has fewer than 10 digits.',
                'sent_at' => now(),
            ]);

            return $sent;
        }

        $adminParams = $sync->templateParamsForEvent($event, $eventName, forAdmin: true);
        $result = $dispatcher->calendarWhatsApp(
            'operational-alerts.calendly.admin_reminder',
            $adminPhone,
            $adminParams,
        );
        $messageId = $this->messageIdFromResult($result);

        CalendlyMessageLog::query()->create([
            'user_id' => $event->user_id,
            'event_id' => $event->id,
            'recipient_type' => 'admin',
            'recipient_number' => $adminDigits,
            'invitee_email' => $event->invitee_email,
            'event_name' => $eventName,
            'event_type' => 'reminder',
            'status' => $result['ok'] ? MessageLogStatus::Sent : MessageLogStatus::Failed,
            'external_message_id' => $messageId,
            'error_message' => $result['ok'] ? null : ($result['error'] ?: 'WhatsApp API did not return success.'),
            'sent_at' => now(),
        ]);

        return $result['ok'] ? $sent + 1 : $sent;
    }

    private function sendGoogleReminder(
        AlertDispatcher $dispatcher,
        GoogleCalendarIntegration $integration,
        GoogleCalendarEvent $event,
        string $eventName,
    ): int {
        $sent = 0;
        $phone = (string) ($event->whatsapp_number ?? '');
        $sync = app(GoogleCalendarEventSyncService::class);
        $customerParams = $sync->templateParamsForEvent($event, $eventName, forAdmin: false);

        if ($phone === '') {
            GoogleCalendarMessageLog::query()->create([
                'user_id' => $event->user_id,
                'event_id' => $event->id,
                'recipient_type' => 'customer',
                'recipient_number' => null,
                'invitee_email' => $event->invitee_email,
                'event_name' => $eventName,
                'event_type' => 'reminder',
                'status' => MessageLogStatus::Skipped,
                'error_message' => 'No WhatsApp number on the event.',
                'sent_at' => now(),
            ]);
        } else {
            $result = $dispatcher->calendarWhatsApp(
                'operational-alerts.google_calendar.customer_reminder',
                $phone,
                $customerParams,
            );
            $messageId = $this->messageIdFromResult($result);

            GoogleCalendarMessageLog::query()->create([
                'user_id' => $event->user_id,
                'event_id' => $event->id,
                'recipient_type' => 'customer',
                'recipient_number' => preg_replace('/\D/', '', $phone) ?: null,
                'invitee_email' => $event->invitee_email,
                'event_name' => $eventName,
                'event_type' => 'reminder',
                'status' => $result['ok'] ? MessageLogStatus::Sent : MessageLogStatus::Failed,
                'external_message_id' => $messageId,
                'error_message' => $result['ok'] ? null : ($result['error'] ?: 'WhatsApp API did not return success.'),
                'sent_at' => now(),
            ]);

            if ($result['ok']) {
                $sent++;
            }
        }

        $adminPhone = (string) ($integration->settings['whatsapp_number'] ?? '');
        if ($adminPhone === '') {
            return $sent;
        }

        $adminDigits = preg_replace('/\D/', '', $adminPhone) ?: '';
        if (strlen($adminDigits) < 10) {
            GoogleCalendarMessageLog::query()->create([
                'user_id' => $event->user_id,
                'event_id' => $event->id,
                'recipient_type' => 'admin',
                'recipient_number' => $adminDigits,
                'invitee_email' => $event->invitee_email,
                'event_name' => $eventName,
                'event_type' => 'reminder',
                'status' => MessageLogStatus::Skipped,
                'error_message' => 'Admin WhatsApp number has fewer than 10 digits.',
                'sent_at' => now(),
            ]);

            return $sent;
        }

        $adminParams = $sync->templateParamsForEvent($event, $eventName, forAdmin: true);
        $result = $dispatcher->calendarWhatsApp(
            'operational-alerts.google_calendar.admin_reminder',
            $adminPhone,
            $adminParams,
        );
        $messageId = $this->messageIdFromResult($result);

        GoogleCalendarMessageLog::query()->create([
            'user_id' => $event->user_id,
            'event_id' => $event->id,
            'recipient_type' => 'admin',
            'recipient_number' => $adminDigits,
            'invitee_email' => $event->invitee_email,
            'event_name' => $eventName,
            'event_type' => 'reminder',
            'status' => $result['ok'] ? MessageLogStatus::Sent : MessageLogStatus::Failed,
            'external_message_id' => $messageId,
            'error_message' => $result['ok'] ? null : ($result['error'] ?: 'WhatsApp API did not return success.'),
            'sent_at' => now(),
        ]);

        return $result['ok'] ? $sent + 1 : $sent;
    }

    /**
     * @param  array{ok?: bool, message_id?: ?string}  $result
     */
    private function messageIdFromResult(array $result): ?string
    {
        $messageId = is_string($result['message_id'] ?? null) ? trim((string) $result['message_id']) : '';
        if ($messageId === '' || ! ($result['ok'] ?? false)) {
            return null;
        }

        try {
            app(WhatsappLineRegistryService::class)
                ->indexMessage((string) (tenant('id') ?? ''), $messageId);
        } catch (\Throwable) {
            // non-fatal
        }

        return $messageId;
    }
}
