<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Console\Commands;

use App\Domains\Alerts\Services\AlertDispatcher;
use App\Domains\ThirdParty\Enums\EventStatus;
use App\Domains\ThirdParty\Models\CalendlyEvent;
use App\Domains\ThirdParty\Models\GoogleCalendarEvent;
use App\Domains\ThirdParty\Models\GoogleCalendarIntegration;
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

                    $phone = (string) ($event->whatsapp_number ?? '');
                    if ($phone !== '') {
                        $dispatcher->calendarWhatsApp(
                            'operational-alerts.calendly.customer_reminder',
                            $phone,
                            [
                                'name' => (string) ($event->invitee_email ?? 'Guest'),
                                'event' => (string) ($event->event_type ?? 'Meeting'),
                                'start' => $event->start_time?->format('d M Y h:i A') ?? '',
                            ],
                        );
                        $sent++;
                    }
                });

            GoogleCalendarEvent::query()
                ->where('status', EventStatus::Active)
                ->whereNotNull('start_time')
                ->whereBetween('start_time', [now(), now()->addDay()])
                ->limit(100)
                ->get()
                ->each(function (GoogleCalendarEvent $event) use ($dispatcher, &$sent): void {
                    $integration = GoogleCalendarIntegration::query()->where('user_id', $event->user_id)->first();
                    $minutes = $integration?->reminderMinutes() ?? 30;
                    if ($event->start_time === null || $event->start_time->greaterThan(now()->addMinutes($minutes))) {
                        return;
                    }

                    $sentDays = (array) ($event->reminder_days_sent ?? []);
                    $dayKey = now()->toDateString();
                    if (in_array($dayKey, $sentDays, true)) {
                        return;
                    }

                    $phone = (string) ($event->whatsapp_number ?? '');
                    if ($phone !== '') {
                        $dispatcher->calendarWhatsApp(
                            'operational-alerts.google_calendar.customer_reminder',
                            $phone,
                            [
                                'name' => (string) ($event->invitee_name ?? $event->summary ?? 'Guest'),
                                'event' => (string) ($event->summary ?? 'Meeting'),
                                'start' => $event->start_time?->format('d M Y h:i A') ?? '',
                            ],
                        );
                    }

                    $event->update([
                        'reminder_days_sent' => array_values(array_unique([...$sentDays, $dayKey])),
                    ]);
                    $sent++;
                });
        });

        $this->info("Calendar reminders processed ({$sent}).");

        return self::SUCCESS;
    }
}
