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

    public function markRead(Request $request, int $notification): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        $model = AdminNotification::query()->find($notification);
        if ($model === null) {
            return redirect()
                ->route('admin.notifications.index')
                ->with('error', 'That notification is no longer available.');
        }

        $this->notifications->markReadFor($admin, (int) $model->id);

        $target = $this->safeAdminPath($request->query('redirect', $request->input('redirect')))
            ?? $this->safeAdminPath($model->link);

        if ($target !== null) {
            return redirect($target);
        }

        return redirect()->route('admin.notifications.index');
    }

    private function safeAdminPath(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        // Absolute URLs → keep only path (+ query).
        if (str_contains($value, '://')) {
            $parts = parse_url($value);
            $path = is_array($parts) ? (string) ($parts['path'] ?? '') : '';
            $query = is_array($parts) && ! empty($parts['query']) ? '?'.$parts['query'] : '';
            $value = $path.$query;
        }

        if ($value === '' || ! str_starts_with($value, '/admin')) {
            return null;
        }

        // Block protocol-relative / open redirects.
        if (str_starts_with($value, '//') || str_contains($value, "\0")) {
            return null;
        }

        return $value;
    }
}
