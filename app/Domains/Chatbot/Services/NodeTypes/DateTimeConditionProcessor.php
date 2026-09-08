<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Services\NodeTypes;

use App\Domains\Chatbot\Enums\NodeProcessResult;
use App\Models\ChatbotFlowState;
use App\Models\Conversation;
use Carbon\Carbon;
use Throwable;

class DateTimeConditionProcessor extends AbstractNodeProcessor
{
    public function process(
        array $node,
        array $nodeMap,
        Conversation $conversation,
        ChatbotFlowState $state,
    ): NodeProcessResult {
        $data = $this->nodeData($node);
        $isOpen = $this->evaluateDateTimeCondition($data);

        // Store evaluation in state variables for downstream nodes
        $variables = $state->variables ?? [];
        $variables['_is_business_hours'] = $isOpen;
        $variables['_is_open'] = $isOpen;
        $variables['_evaluated_at'] = now()->toIso8601String();

        $nextId = $isOpen
            ? ($this->nextNodeIdFromHandle($node, 'open')
                ?? $this->nextNodeIdFromHandle($node, 'output_open')
                ?? $this->nextNodeIdFromHandle($node, 'output_yes')
                ?? $this->nextNodeIdFromHandle($node, 'output_true')
                ?? $this->nextNodeIdFromHandle($node, 'yes')
                ?? $this->defaultNextNodeId($node))
            : ($this->nextNodeIdFromHandle($node, 'closed')
                ?? $this->nextNodeIdFromHandle($node, 'output_closed')
                ?? $this->nextNodeIdFromHandle($node, 'output_no')
                ?? $this->nextNodeIdFromHandle($node, 'output_false')
                ?? $this->nextNodeIdFromHandle($node, 'no')
                ?? $this->nextNodeIdFromHandle($node, 'output_2')
                ?? $this->defaultNextNodeId($node));

        if ($nextId !== null) {
            $state->forceFill([
                'current_node_id' => $nextId,
                'variables' => $variables,
            ])->save();
        }

        return $nextId !== null ? NodeProcessResult::Continue : NodeProcessResult::Completed;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function evaluateDateTimeCondition(array $data): bool
    {
        $timezone = (string) ($data['timezone'] ?? config('app.timezone', 'Asia/Kolkata'));

        try {
            $now = Carbon::now($timezone);
        } catch (Throwable) {
            $now = Carbon::now('UTC');
        }

        $mode = (string) ($data['mode'] ?? $data['conditionType'] ?? $data['condition_type'] ?? 'business_hours');

        return match ($mode) {
            'time_range' => $this->evaluateTimeRange($now, $data),
            'days_of_week' => $this->evaluateDaysOfWeek($now, $data),
            'date_range' => $this->evaluateDateRange($now, $data),
            default => $this->evaluateBusinessHours($now, $data),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function evaluateBusinessHours(Carbon $now, array $data): bool
    {
        // 1. Holiday check
        $holidays = (array) ($data['holidays'] ?? []);
        $currentDate = $now->format('Y-m-d');
        $currentMonthDay = $now->format('m-d');

        foreach ($holidays as $holiday) {
            $holidayStr = trim((string) $holiday);
            if ($holidayStr === $currentDate || $holidayStr === $currentMonthDay) {
                return false; // Closed on holidays
            }
        }

        $currentDayName = strtolower($now->format('l')); // 'monday', 'tuesday', etc.
        $currentDayShort = strtolower($now->format('D')); // 'mon', 'tue', etc.
        $schedule = (array) ($data['schedule'] ?? []);

        // Per-day custom schedule check
        if (! empty($schedule)) {
            $dayConfig = $schedule[$currentDayName] ?? $schedule[$currentDayShort] ?? null;

            if ($dayConfig === null || empty($dayConfig['enabled'])) {
                return false;
            }

            $startTime = (string) ($dayConfig['start'] ?? $dayConfig['startTime'] ?? '09:00');
            $endTime = (string) ($dayConfig['end'] ?? $dayConfig['endTime'] ?? '18:00');

            return $this->isWithinTime($now, $startTime, $endTime);
        }

        // Global weekly schedule check
        $enabledDays = (array) ($data['enabled_days'] ?? $data['enabledDays'] ?? [
            'monday', 'tuesday', 'wednesday', 'thursday', 'friday',
        ]);

        $enabledDaysLower = array_map(fn ($d) => strtolower(trim((string) $d)), $enabledDays);

        if (! in_array($currentDayName, $enabledDaysLower, true) && ! in_array($currentDayShort, $enabledDaysLower, true)) {
            return false;
        }

        $startTime = (string) ($data['start_time'] ?? $data['startTime'] ?? '09:00');
        $endTime = (string) ($data['end_time'] ?? $data['endTime'] ?? '18:00');

        return $this->isWithinTime($now, $startTime, $endTime);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function evaluateTimeRange(Carbon $now, array $data): bool
    {
        $startTime = (string) ($data['start_time'] ?? $data['startTime'] ?? '00:00');
        $endTime = (string) ($data['end_time'] ?? $data['endTime'] ?? '23:59');

        return $this->isWithinTime($now, $startTime, $endTime);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function evaluateDaysOfWeek(Carbon $now, array $data): bool
    {
        $days = (array) ($data['selected_days'] ?? $data['selectedDays'] ?? $data['enabled_days'] ?? []);
        $daysLower = array_map(fn ($d) => strtolower(trim((string) $d)), $days);

        $currentDayName = strtolower($now->format('l'));
        $currentDayShort = strtolower($now->format('D'));

        return in_array($currentDayName, $daysLower, true) || in_array($currentDayShort, $daysLower, true);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function evaluateDateRange(Carbon $now, array $data): bool
    {
        $startDate = (string) ($data['start_date'] ?? $data['startDate'] ?? '');
        $endDate = (string) ($data['end_date'] ?? $data['endDate'] ?? '');

        if ($startDate !== '' && $now->toDateString() < $startDate) {
            return false;
        }

        if ($endDate !== '' && $now->toDateString() > $endDate) {
            return false;
        }

        return true;
    }

    private function isWithinTime(Carbon $now, string $startTime, string $endTime): bool
    {
        $currentMinutes = $now->hour * 60 + $now->minute;

        $startParts = explode(':', $startTime);
        $startMinutes = ((int) ($startParts[0] ?? 0)) * 60 + ((int) ($startParts[1] ?? 0));

        $endParts = explode(':', $endTime);
        $endMinutes = ((int) ($endParts[0] ?? 23)) * 60 + ((int) ($endParts[1] ?? 59));

        if ($startMinutes <= $endMinutes) {
            // Same-day range (e.g. 09:00 to 18:00)
            return $currentMinutes >= $startMinutes && $currentMinutes <= $endMinutes;
        }

        // Overnight range (e.g. 22:00 to 06:00)
        return $currentMinutes >= $startMinutes || $currentMinutes <= $endMinutes;
    }
}
