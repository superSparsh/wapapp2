<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Domains\Admin\Services\AdminNotificationService;
use App\Models\Admin;
use Illuminate\View\View;

class AdminHeaderComposer
{
    public function __construct(
        private readonly AdminNotificationService $notifications,
    ) {}

    public function compose(View $view): void
    {
        $admin = auth('admin')->user();
        if (! $admin instanceof Admin) {
            $view->with([
                'adminNotificationCount' => 0,
                'adminNotifications' => collect(),
            ]);

            return;
        }

        $view->with([
            'adminNotificationCount' => $this->notifications->unreadCountFor($admin),
            'adminNotifications' => $this->notifications->recentFor($admin, 12),
        ]);
    }
}
