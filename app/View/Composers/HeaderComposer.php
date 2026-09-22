<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Domains\Account\Services\NotificationService;
use App\Domains\Admin\Support\AdminSession;
use App\Domains\Admin\Support\AdminViewAccess;
use App\Domains\Team\Services\TeamImpersonationService;
use App\Models\TenantUserAccess;
use App\Support\CurrentAccount;
use Illuminate\View\View;

class HeaderComposer
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly TeamImpersonationService $impersonationService,
    ) {}

    public function compose(View $view): void
    {
        $adminImpersonation = AdminSession::impersonation();
        $adminEmail = strtolower(trim((string) ($adminImpersonation['admin_email'] ?? '')));
        $currentEmail = strtolower(trim((string) CurrentAccount::email()));
        $impersonatedTenantId = (string) ($adminImpersonation['tenant_id'] ?? '');
        $isAdminOwnCustomer = $adminImpersonation !== null
            && $adminEmail !== ''
            && (
                ($currentEmail !== '' && $adminEmail === $currentEmail)
                || ($impersonatedTenantId !== '' && TenantUserAccess::query()
                    ->whereRaw('LOWER(email) = ?', [$adminEmail])
                    ->where('tenant_id', $impersonatedTenantId)
                    ->where('is_active', true)
                    ->exists())
            );

        $view->with([
            'currentAccountName' => CurrentAccount::displayName(),
            'currentAccountEmail' => CurrentAccount::email(),
            'currentAccountAvatar' => CurrentAccount::avatarUrl(),
            'notificationCount' => $this->notificationService->unreadCount(),
            'notifications' => $this->notificationService->recent(),
            'isImpersonating' => $this->impersonationService->isImpersonating(),
            'impersonatedMember' => $this->impersonationService->impersonatedMember(),
            'isAdminImpersonating' => $adminImpersonation !== null,
            'adminImpersonatorName' => $adminImpersonation['admin_name'] ?? null,
            'adminImpersonatedTenantName' => $adminImpersonation['tenant_name'] ?? null,
            'canAccessAdminView' => AdminViewAccess::canAccess(),
            'isAdminOwnCustomer' => $isAdminOwnCustomer,
        ]);
    }
}
