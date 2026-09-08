<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Domains\Account\Services\NotificationService;
use App\Domains\Admin\Support\AdminSession;
use App\Domains\Team\Services\TeamImpersonationService;
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
        ]);
    }
}
