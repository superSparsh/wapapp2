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
                    $location = (string) (
                        $event->raw_payload['location']['join_url']
                        ?? $event->raw_payload['location']['location']
                        ?? ''
                    );
                    $params = [
                        'name'  => (string) ($event->invitee_email ?? 'Guest'),
                        'event' => $eventName,
                        'start' => $event->start_time?->format('d M Y h:i A') ?? '',
                        'meet'  => $location,
                        'link'  => $location,
                    ];

                    $sent += $this->sendCalendlyReminder($dispatcher, $integration, $event, $eventName, $params);
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
                    $meet = (string) ($event->meet_link ?? '');
                    $params = [
                        'name'  => (string) ($event->invitee_name ?? $event->summary ?? 'Guest'),
                        'event' => $eventName,
                        'start' => $event->start_time?->format('d M Y h:i A') ?? '',
                        'meet'  => $meet,
                        'link'  => (string) ($meet ?: $event->calendar_link ?: ''),
                    ];

                    $sent += $this->sendGoogleReminder($dispatcher, $integration, $event, $eventName, $params);

                    $event->update([
                        'reminder_days_sent' => array_values(array_unique([...$sentDays, $dayKey])),
                    ]);
                });
        });

        $this->info("Calendar reminders processed ({$sent}).");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $params
     */
    private function sendCalendlyReminder(
        AlertDispatcher $dispatcher,
        CalendlyIntegration $integration,
        CalendlyEvent $event,
        string $eventName,
        array $params,
    ): int {
        $sent = 0;
        $phone = (string) ($event->whatsapp_number ?? '');

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
            $ok = $dispatcher->calendarWhatsApp(
                'operational-alerts.calendly.customer_reminder',
                $phone,
                $params,
            );

            CalendlyMessageLog::query()->create([
                'user_id' => $event->user_id,
                'event_id' => $event->id,
                'recipient_type' => 'customer',
                'recipient_number' => preg_replace('/\D/', '', $phone) ?: null,
                'invitee_email' => $event->invitee_email,
                'event_name' => $eventName,
                'event_type' => 'reminder',
                'status' => $ok ? MessageLogStatus::Sent : MessageLogStatus::Failed,
                'error_message' => $ok ? null : 'WhatsApp API did not return success.',
                'sent_at' => now(),
            ]);

            if ($ok) {
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

        $ok = $dispatcher->calendarWhatsApp(
            'operational-alerts.calendly.admin_reminder',
            $adminPhone,
            $params,
        );

        CalendlyMessageLog::query()->create([
            'user_id' => $event->user_id,
            'event_id' => $event->id,
            'recipient_type' => 'admin',
            'recipient_number' => $adminDigits,
            'invitee_email' => $event->invitee_email,
            'event_name' => $eventName,
            'event_type' => 'reminder',
            'status' => $ok ? MessageLogStatus::Sent : MessageLogStatus::Failed,
            'error_message' => $ok ? null : 'WhatsApp API did not return success.',
            'sent_at' => now(),
        ]);

        return $ok ? $sent + 1 : $sent;
    }

    /**
     * @param  array<string, string>  $params
     */
    private function sendGoogleReminder(
        AlertDispatcher $dispatcher,
        GoogleCalendarIntegration $integration,
        GoogleCalendarEvent $event,
        string $eventName,
        array $params,
    ): int {
        $sent = 0;
        $phone = (string) ($event->whatsapp_number ?? '');

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
            $ok = $dispatcher->calendarWhatsApp(
                'operational-alerts.google_calendar.customer_reminder',
                $phone,
                $params,
            );

            GoogleCalendarMessageLog::query()->create([
                'user_id' => $event->user_id,
                'event_id' => $event->id,
                'recipient_type' => 'customer',
                'recipient_number' => preg_replace('/\D/', '', $phone) ?: null,
                'invitee_email' => $event->invitee_email,
                'event_name' => $eventName,
                'event_type' => 'reminder',
                'status' => $ok ? MessageLogStatus::Sent : MessageLogStatus::Failed,
                'error_message' => $ok ? null : 'WhatsApp API did not return success.',
                'sent_at' => now(),
            ]);

            if ($ok) {
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

        $ok = $dispatcher->calendarWhatsApp(
            'operational-alerts.google_calendar.admin_reminder',
            $adminPhone,
            $params,
        );

        GoogleCalendarMessageLog::query()->create([
            'user_id' => $event->user_id,
            'event_id' => $event->id,
            'recipient_type' => 'admin',
            'recipient_number' => $adminDigits,
            'invitee_email' => $event->invitee_email,
            'event_name' => $eventName,
            'event_type' => 'reminder',
            'status' => $ok ? MessageLogStatus::Sent : MessageLogStatus::Failed,
            'error_message' => $ok ? null : 'WhatsApp API did not return success.',
            'sent_at' => now(),
        ]);

        return $ok ? $sent + 1 : $sent;
    }
}
