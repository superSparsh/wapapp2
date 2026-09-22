<?php

declare(strict_types=1);

namespace App\Domains\Admin\Services;

use App\Enums\AdminNotificationType;
use App\Models\Admin;
use App\Models\AdminNotification;
use App\Models\AdminNotificationRead;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class AdminNotificationService
{
    private const ERROR_THROTTLE_SECONDS = 900;

    /**
     * @param  array<string, mixed>  $data
     */
    public function notify(
        AdminNotificationType|string $type,
        string $title,
        ?string $body = null,
        ?string $link = null,
        array $data = [],
    ): ?AdminNotification {
        try {
            if (! $this->tableReady()) {
                return null;
            }

            $typeEnum = $type instanceof AdminNotificationType
                ? $type
                : AdminNotificationType::tryFrom($type) ?? AdminNotificationType::System;

            return AdminNotification::query()->create([
                'type' => $typeEnum,
                'title' => Str::limit(trim($title), 180, '…'),
                'body' => $body !== null ? Str::limit(trim($body), 1000, '…') : null,
                'link' => $link !== null ? Str::limit($this->normalizeAdminLink($link), 500, '') : null,
                'data' => $data !== [] ? $data : null,
            ]);
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizeAdminLink(string $link): string
    {
        $link = trim($link);
        if ($link === '') {
            return '';
        }

        if (str_contains($link, '://')) {
            $parts = parse_url($link);
            $path = is_array($parts) ? (string) ($parts['path'] ?? '') : '';
            $query = is_array($parts) && ! empty($parts['query']) ? '?'.$parts['query'] : '';
            $link = $path.$query;
        }

        return str_starts_with($link, '/admin') ? $link : '';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function notifyNewCustomer(string $tenantId, string $name, string $email): ?AdminNotification
    {
        $link = null;
        try {
            $link = route('admin.customers.show', $tenantId);
        } catch (Throwable) {
            $link = '/admin/customers/'.$tenantId;
        }

        return $this->notify(
            AdminNotificationType::NewCustomer,
            'New customer registered',
            "{$name} ({$email}) joined the platform.",
            $link,
            ['tenant_id' => $tenantId, 'email' => $email],
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function notifyPlatformError(
        string $module,
        string $type,
        string $message,
        ?string $tenantId = null,
        ?int $errorLogId = null,
    ): ?AdminNotification {
        $hash = substr(sha1($module.'|'.$type.'|'.mb_strtolower(trim($message))), 0, 16);
        $cacheKey = "admin_notif_error:{$module}:{$hash}";

        if (! Cache::add($cacheKey, 1, self::ERROR_THROTTLE_SECONDS)) {
            return null;
        }

        $link = null;
        try {
            $link = $errorLogId
                ? route('admin.errors.detail', $errorLogId)
                : route('admin.errors.show', ['module' => $module]);
        } catch (Throwable) {
            $link = '/admin/errors';
        }

        $title = 'Platform error: '.$module;
        $body = Str::limit("[{$type}] {$message}", 240, '…');
        if ($tenantId) {
            $body .= " — tenant {$tenantId}";
        }

        return $this->notify(
            AdminNotificationType::PlatformError,
            $title,
            $body,
            $link,
            [
                'module' => $module,
                'error_type' => $type,
                'tenant_id' => $tenantId,
                'error_log_id' => $errorLogId,
            ],
        );
    }

    public function notifyRenewRequest(int $requestId, string $tenantLabel, ?string $planName = null): ?AdminNotification
    {
        $link = null;
        try {
            $link = route('admin.renew-requests.index');
        } catch (Throwable) {
            $link = '/admin/renew-requests';
        }

        $body = $planName
            ? "{$tenantLabel} requested renewal for {$planName}."
            : "{$tenantLabel} submitted a renewal request.";

        return $this->notify(
            AdminNotificationType::RenewRequest,
            'New renew request',
            $body,
            $link,
            ['request_id' => $requestId],
        );
    }

    public function notifyRechargeRequest(int $requestId, string $tenantLabel, ?string $amount = null): ?AdminNotification
    {
        $link = null;
        try {
            $link = route('admin.recharge-requests.index');
        } catch (Throwable) {
            $link = '/admin/recharge-requests';
        }

        $body = $amount
            ? "{$tenantLabel} requested a wallet recharge of {$amount}."
            : "{$tenantLabel} submitted a wallet recharge request.";

        return $this->notify(
            AdminNotificationType::RechargeRequest,
            'New recharge request',
            $body,
            $link,
            ['request_id' => $requestId],
        );
    }

    /**
     * @return Collection<int, AdminNotification>
     */
    public function recentFor(Admin $admin, int $limit = 20): Collection
    {
        if (! $this->tableReady()) {
            return collect();
        }

        $readIds = AdminNotificationRead::query()
            ->where('admin_id', $admin->id)
            ->pluck('admin_notification_id');

        return AdminNotification::query()
            ->whereNotIn('id', $readIds)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function unreadCountFor(Admin $admin): int
    {
        if (! $this->tableReady()) {
            return 0;
        }

        $readIds = AdminNotificationRead::query()
            ->where('admin_id', $admin->id)
            ->pluck('admin_notification_id');

        return (int) AdminNotification::query()
            ->whereNotIn('id', $readIds)
            ->count();
    }

    public function markAllReadFor(Admin $admin): int
    {
        if (! $this->tableReady()) {
            return 0;
        }

        $unreadIds = AdminNotification::query()
            ->whereNotIn('id', AdminNotificationRead::query()
                ->where('admin_id', $admin->id)
                ->select('admin_notification_id'))
            ->pluck('id');

        $now = now();
        $rows = $unreadIds->map(fn ($id) => [
            'admin_id' => $admin->id,
            'admin_notification_id' => $id,
            'read_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        if ($rows === []) {
            return 0;
        }

        AdminNotificationRead::query()->insertOrIgnore($rows);

        return count($rows);
    }

    public function markReadFor(Admin $admin, int $notificationId): void
    {
        if (! $this->tableReady()) {
            return;
        }

        AdminNotificationRead::query()->firstOrCreate(
            [
                'admin_id' => $admin->id,
                'admin_notification_id' => $notificationId,
            ],
            ['read_at' => now()],
        );
    }

    private function tableReady(): bool
    {
        try {
            return Schema::connection(
                (string) config('tenancy.database.central_connection', config('database.default'))
            )->hasTable('admin_notifications');
        } catch (Throwable) {
            return false;
        }
    }
}
