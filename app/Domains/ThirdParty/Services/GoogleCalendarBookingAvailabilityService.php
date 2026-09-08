<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Services;

use App\Domains\ThirdParty\Models\GoogleCalendarIntegration;
use Carbon\Carbon;
use Carbon\CarbonInterval;

/**
 * Manages booking availability (weekly schedule) and slot calculation for public booking pages.
 *
 * Optimization: slot calculation uses an in-memory cache per request via a local property.
 */
class GoogleCalendarBookingAvailabilityService
{
    private const DAYS = [0, 1, 2, 3, 4, 5, 6]; // 0=Sunday, 6=Saturday

    /** @var array<string, array<int, array{start: string, end: string}>> */
    private array $slotCache = [];

    /**
     * Normalize raw availability input from the form into a clean weekly structure.
     *
     * @param array<mixed, mixed> $raw
     * @return array{weekly: array<int, array{enabled: bool, intervals: array<int, array{start: string, end: string}>}>}
     */
    public function normalizeAvailability(array $raw): array
    {
        // Accept both ['weekly' => [...]] and direct day-keyed arrays
        if (isset($raw['weekly']) && is_array($raw['weekly'])) {
            $raw = $raw['weekly'];
        }

        $weekly = [];

        foreach (self::DAYS as $day) {
            $dayData = $raw[$day] ?? $raw[(string) $day] ?? [];

            $enabled   = ! empty($dayData['enabled']);
            $intervals = [];

            foreach ((array) ($dayData['intervals'] ?? []) as $interval) {
                $start = (string) ($interval['start'] ?? '');
                $end   = (string) ($interval['end'] ?? '');

                // Normalize to HH:MM
                if ($start && $end) {
                    $intervals[] = [
                        'start' => $this->normalizeTime($start),
                        'end'   => $this->normalizeTime($end),
                    ];
                }
            }

            $weekly[$day] = [
                'enabled'   => $enabled,
                'intervals' => $intervals,
            ];
        }

        return ['weekly' => $weekly];
    }

    /**
     * Resolve the booking availability structure from the integration's settings.
     *
     * @return array{weekly: array<int, array{enabled: bool, intervals: array<int, array{start: string, end: string}>}>}
     */
    public function resolveFromIntegration(GoogleCalendarIntegration $integration): array
    {
        $raw = $integration->settings['booking_availability'] ?? null;

        if (is_array($raw) && isset($raw['weekly'])) {
            return $raw;
        }

        return $this->defaultAvailability();
    }

    /**
     * Calculate available booking slots for a specific date and duration.
     * Uses in-memory cache keyed by date + duration to avoid repeated freeBusy calls.
     *
     * @return array<int, array{start: string, end: string, label: string}>
     */
    public function getAvailableSlots(
        GoogleCalendarIntegration $integration,
        GoogleCalendarApiService $api,
        Carbon $date,
        int $durationMinutes,
    ): array {
        $cacheKey = $date->toDateString() . ':' . $durationMinutes;

        if (isset($this->slotCache[$cacheKey])) {
            return $this->slotCache[$cacheKey];
        }

        $availability = $this->resolveFromIntegration($integration);
        $dayOfWeek    = (int) $date->dayOfWeek; // 0=Sunday
        $daySchedule  = $availability['weekly'][$dayOfWeek] ?? [];

        if (empty($daySchedule['enabled']) || empty($daySchedule['intervals'])) {
            return $this->slotCache[$cacheKey] = [];
        }

        // Build candidate slots from the schedule
        $candidates = [];
        foreach ($daySchedule['intervals'] as $interval) {
            $windowStart = Carbon::parse($date->toDateString() . ' ' . $interval['start'], 'UTC');
            $windowEnd   = Carbon::parse($date->toDateString() . ' ' . $interval['end'], 'UTC');

            $cursor = $windowStart->copy();
            while ($cursor->copy()->addMinutes($durationMinutes)->lte($windowEnd)) {
                $slotEnd       = $cursor->copy()->addMinutes($durationMinutes);
                $candidates[]  = ['start' => $cursor->toRfc3339String(), 'end' => $slotEnd->toRfc3339String()];
                $cursor->addMinutes($durationMinutes);
            }
        }

        if (empty($candidates)) {
            return $this->slotCache[$cacheKey] = [];
        }

        // Subtract busy periods from Google freeBusy
        $dayStart  = $date->copy()->startOfDay()->utc();
        $dayEnd    = $date->copy()->endOfDay()->utc();
        $busyPeriods = $api->getFreeBusyPeriods($integration, $dayStart, $dayEnd) ?? [];

        $available = array_values(array_filter($candidates, function (array $slot) use ($busyPeriods): bool {
            $sStart = Carbon::parse($slot['start']);
            $sEnd   = Carbon::parse($slot['end']);

            foreach ($busyPeriods as $busy) {
                $bStart = Carbon::parse($busy['start']);
                $bEnd   = Carbon::parse($busy['end']);

                if ($sStart->lt($bEnd) && $sEnd->gt($bStart)) {
                    return false; // overlaps
                }
            }

            return $sStart->isFuture();
        }));

        // Add a human-readable label
        foreach ($available as &$slot) {
            $slot['label'] = Carbon::parse($slot['start'])->format('h:i A') . ' - ' . Carbon::parse($slot['end'])->format('h:i A');
        }

        return $this->slotCache[$cacheKey] = $available;
    }

    /**
     * Validate that each enabled day has at least one interval and intervals are well-formed.
     */
    public function validate(array $normalized): ?string
    {
        $enabledDays = 0;
        foreach ($normalized['weekly'] as $day) {
            if (empty($day['enabled'])) {
                continue;
            }
            if (empty($day['intervals'])) {
                return 'Add at least one time range for each enabled day.';
            }
            foreach ($day['intervals'] as $interval) {
                if ($interval['end'] <= $interval['start']) {
                    return 'Each time range must end after it starts.';
                }
            }
            $enabledDays++;
        }

        if ($enabledDays === 0) {
            return 'Enable at least one day with available hours.';
        }

        return null;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @return array{weekly: array<int, array{enabled: bool, intervals: array<int, array{start: string, end: string}>}>}
     */
    private function defaultAvailability(): array
    {
        $weekly = [];
        foreach (self::DAYS as $day) {
            $isWeekday = $day >= 1 && $day <= 5;
            $weekly[$day] = [
                'enabled'   => $isWeekday,
                'intervals' => $isWeekday ? [['start' => '09:00', 'end' => '17:00']] : [],
            ];
        }
        return ['weekly' => $weekly];
    }

    private function normalizeTime(string $time): string
    {
        // Accept "HH:MM" or "HH:MM:SS" or Carbon-parseable
        try {
            return Carbon::parse('2000-01-01 ' . $time)->format('H:i');
        } catch (\Throwable) {
            return $time;
        }
    }
}
