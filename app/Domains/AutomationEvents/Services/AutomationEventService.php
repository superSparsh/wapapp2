<?php

declare(strict_types=1);

namespace App\Domains\AutomationEvents\Services;

use App\Models\AutomationEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AutomationEventService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return AutomationEvent::query()
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): AutomationEvent
    {
        return AutomationEvent::query()->create([
            'name' => (string) ($data['name'] ?? 'Automation Event'),
            'status' => (string) ($data['status'] ?? 'scheduled'),
            'event_type' => (string) $data['event_type'],
            'payload' => (array) ($data['payload'] ?? []),
            'scheduled_at' => $data['scheduled_at'] ?? now(),
            'created_by' => $data['created_by'] ?? auth('team')->id() ?? auth('web')->id(),
        ]);
    }

    public function destroy(AutomationEvent $event): void
    {
        $event->delete();
    }

    public function markProcessed(AutomationEvent $event): void
    {
        $event->forceFill([
            'status' => 'processed',
            'processed_at' => now(),
        ])->save();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, AutomationEvent>
     */
    public function dueEvents(int $limit = 100)
    {
        return AutomationEvent::query()
            ->where('status', 'scheduled')
            ->where(function ($query): void {
                $query->whereNull('scheduled_at')
                    ->orWhere('scheduled_at', '<=', now());
            })
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->get();
    }
}
