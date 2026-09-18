<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Domains\Inbox\Services\InboxQueryService;
use App\Domains\Team\Support\TeamActor;
use App\Domains\Team\Support\TeamNavigation;
use Illuminate\View\View;

class SidebarComposer
{
    public function __construct(
        private readonly InboxQueryService $inboxQueryService,
    ) {}

    public function compose(View $view): void
    {
        $unread = 0;

        try {
            if (tenancy()->initialized) {
                $unread = $this->inboxQueryService->totalUnreadCount();
            }
        } catch (\Throwable) {
            $unread = 0;
        }

        $member = TeamActor::teamMember();
        $items = TeamNavigation::items($member);

        $navItems = collect($items)
            ->map(function (array $item) use ($unread): array {
                if (($item['route'] ?? '') === 'inbox.index' && $unread > 0) {
                    $item['badge'] = $unread > 99 ? '99+' : (string) $unread;
                }

                return $item;
            })
            ->all();

        $view->with('navItems', $navItems);
    }
}
