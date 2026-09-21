<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Services\AdminNotificationService;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminNotificationController extends Controller
{
    public function __construct(
        private readonly AdminNotificationService $notifications,
    ) {}

    public function index(Request $request): View
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        return view('admin.notifications.index', [
            'notifications' => $this->notifications->recentFor($admin, 50),
            'unreadCount' => $this->notifications->unreadCountFor($admin),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse|RedirectResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $count = $this->notifications->markAllReadFor($admin);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'marked' => $count]);
        }

        return back()->with('status', $count > 0 ? "Marked {$count} notification(s) as read." : 'No unread notifications.');
    }

    public function markRead(Request $request, AdminNotification $notification): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $this->notifications->markReadFor($admin, (int) $notification->id);

        $redirect = $request->query('redirect', $request->input('redirect'));
        if (is_string($redirect) && str_starts_with($redirect, '/admin')) {
            return redirect($redirect);
        }

        if (is_string($notification->link) && str_starts_with((string) parse_url($notification->link, PHP_URL_PATH), '/admin')) {
            return redirect($notification->link);
        }

        return redirect()->route('admin.notifications.index');
    }
}
