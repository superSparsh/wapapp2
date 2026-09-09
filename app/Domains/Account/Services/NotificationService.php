<?php

declare(strict_types=1);

namespace App\Domains\Account\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Collection;

class NotificationService
{
    public function unreadCount(): int
    {
        $readAt = session('notifications.read_at');

        return ActivityLog::query()
            ->when($readAt, fn ($query) => $query->where('created_at', '>', $readAt))
            ->count();
    }

    /** @return Collection<int, ActivityLog> Unread (new) notifications only — never re-show after mark-as-read. */
    public function recent(int $limit = 8): Collection
    {
        $readAt = session('notifications.read_at');

        return ActivityLog::query()
            ->when($readAt, fn ($query) => $query->where('created_at', '>', $readAt))
            ->latest('created_at')
            ->limit($limit)
            ->get([
                'id',
                'uid',
                'action',
                'description',
                'scope',
                'created_at',
            ]);
    }

    public function markAsRead(): void
    {
        session(['notifications.read_at' => now()]);
    }
}
