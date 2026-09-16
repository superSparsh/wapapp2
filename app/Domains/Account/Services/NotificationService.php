<?php

declare(strict_types=1);

namespace App\Domains\Account\Services;

use App\Models\AccountPreference;
use App\Models\ActivityLog;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class NotificationService
{
    public function unreadCount(): int
    {
        $readAt = $this->readAt();

        return ActivityLog::query()
            ->when($readAt, fn ($query) => $query->where('created_at', '>', $readAt))
            ->count();
    }

    /** @return Collection<int, ActivityLog> Unread (new) notifications only — never re-show after mark-as-read. */
    public function recent(int $limit = 8): Collection
    {
        $readAt = $this->readAt();

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
        $now = now();

        AccountPreference::current()->update([
            'notifications_read_at' => $now,
        ]);

        // Keep session in sync for the current browser session.
        session(['notifications.read_at' => $now->toIso8601String()]);
    }

    private function readAt(): ?CarbonInterface
    {
        $preference = AccountPreference::current()->notifications_read_at;

        if ($preference instanceof CarbonInterface) {
            return $preference;
        }

        $sessionValue = session('notifications.read_at');
        if ($sessionValue instanceof CarbonInterface) {
            return $sessionValue;
        }

        if (is_string($sessionValue) && $sessionValue !== '') {
            try {
                return \Illuminate\Support\Carbon::parse($sessionValue);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
