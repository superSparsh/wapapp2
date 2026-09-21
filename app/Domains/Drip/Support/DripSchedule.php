<?php

declare(strict_types=1);

namespace App\Domains\Drip\Support;

use App\Models\Contact;
use App\Models\DripCampaign;
use Illuminate\Support\Carbon;

/**
 * Decides when a saved drip trigger is due, using the same option keys the settings form stores.
 */
final class DripSchedule
{
    public function isDue(DripCampaign $campaign, Contact $contact, ?Carbon $now = null): bool
    {
        return $this->enrollmentKey($campaign, $contact, $now) !== null;
    }

    /**
     * Stable key for this occurrence, or null when the contact is not due.
     */
    public function enrollmentKey(DripCampaign $campaign, Contact $contact, ?Carbon $now = null): ?string
    {
        $timezone = (string) ($campaign->timezone ?: config('app.timezone', 'UTC'));
        $now = ($now ?? now())->copy()->timezone($timezone);
        $options = (array) ($campaign->trigger_options ?? []);
        $type = DripTriggerCatalog::normalizeType((string) $campaign->trigger_type);

        return match ($type) {
            'specific-date' => $this->specificDateKey($options, $now),
            'weekly-recurring' => $this->weeklyKey($options, $now),
            'monthly-recurring' => $this->monthlyKey($options, $now),
            'say-happy-birthday' => $this->contactDateKey($contact, $options, $now, yearly: true, prefix: 'birthday'),
            'subscriber-added-date' => $this->subscriberAddedKey($contact, $options, $now),
            'specific-date-time-of-user' => $this->contactDateKey($contact, $options, $now, yearly: false, prefix: 'user-date'),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function specificDateKey(array $options, Carbon $now): ?string
    {
        $date = (string) ($options['date'] ?? $options['scheduled_at'] ?? '');
        if ($date === '') {
            return null;
        }

        $target = $this->atClock(Carbon::parse($date, $now->timezoneName), (string) ($options['at'] ?? $options['time'] ?? '00:00'));

        if (! $target->isSameMinute($now)) {
            return null;
        }

        return 'date-'.$target->format('Y-m-d-H-i');
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function weeklyKey(array $options, Carbon $now): ?string
    {
        $days = array_map('intval', (array) ($options['days_of_week'] ?? []));
        if ($days === [] && filled($options['day_of_week'] ?? $options['weekday'] ?? null)) {
            $name = strtolower((string) ($options['day_of_week'] ?? $options['weekday']));
            $map = array_map('strtolower', Carbon::getDays());
            $index = array_search($name, $map, true);
            if ($index !== false) {
                $days = [(int) $index];
            }
        }

        $time = (string) ($options['at'] ?? $options['time'] ?? '09:00');
        if ($days === [] || ! in_array($now->dayOfWeek, $days, true) || ! $this->sameClockMinute($now, $time)) {
            return null;
        }

        return 'week-'.$now->toDateString();
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function monthlyKey(array $options, Carbon $now): ?string
    {
        $days = array_map('intval', (array) ($options['days_of_month'] ?? []));
        if ($days === [] && filled($options['day_of_month'] ?? $options['day'] ?? null)) {
            $days = [(int) ($options['day_of_month'] ?? $options['day'])];
        }

        $time = (string) ($options['at'] ?? $options['time'] ?? '09:00');
        if ($days === [] || ! in_array($now->day, $days, true) || ! $this->sameClockMinute($now, $time)) {
            return null;
        }

        return 'month-'.$now->toDateString();
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function subscriberAddedKey(Contact $contact, array $options, Carbon $now): ?string
    {
        if ($contact->created_at === null) {
            return null;
        }

        $joined = $contact->created_at->copy()->timezone($now->timezoneName);
        $occurrence = $this->applyBefore(
            $this->atClock($joined->copy()->year($now->year), (string) ($options['at'] ?? '10:00')),
            (string) ($options['delay'] ?? $options['before'] ?? '0 day'),
        );

        if (! $occurrence->isSameMinute($now) || ! $occurrence->greaterThan($joined->copy()->addDay())) {
            return null;
        }

        return 'joined-'.$now->year;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function contactDateKey(Contact $contact, array $options, Carbon $now, bool $yearly, string $prefix): ?string
    {
        $field = (string) ($options['field'] ?? 'date_of_birth');
        $raw = data_get($contact->custom_fields, $field) ?? data_get($contact->metadata, $field);
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            $parsed = Carbon::parse($raw, $now->timezoneName);
        } catch (\Throwable) {
            return null;
        }

        $base = $yearly ? $parsed->copy()->year($now->year) : $parsed->copy();
        $occurrence = $this->applyBefore(
            $this->atClock($base, (string) ($options['at'] ?? '10:00')),
            (string) ($options['before'] ?? '0 day'),
        );

        if (! $occurrence->isSameMinute($now)) {
            return null;
        }

        return $prefix.'-'.($yearly ? (string) $now->year : $parsed->toDateString());
    }

    private function atClock(Carbon $date, string $time): Carbon
    {
        [$hour, $minute] = array_pad(explode(':', $time), 2, '0');

        return $date->copy()->setTime((int) $hour, (int) $minute);
    }

    private function sameClockMinute(Carbon $now, string $time): bool
    {
        [$hour, $minute] = array_pad(explode(':', $time), 2, '0');

        return $now->hour === (int) $hour && $now->minute === (int) $minute;
    }

    private function applyBefore(Carbon $moment, string $before): Carbon
    {
        $before = strtolower(trim($before));
        if ($before === '' || preg_match('/^0\b/', $before) === 1) {
            return $moment;
        }

        if (preg_match('/^(\d+)\s+(day|days|week|weeks|month|months)$/', $before, $matches) !== 1) {
            return $moment;
        }

        return $moment->copy()->modify('-'.$matches[1].' '.$matches[2]);
    }
}
