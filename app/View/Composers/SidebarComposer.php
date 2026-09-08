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

        if (tenancy()->initialized) {
            $line = $this->inboxQueryService->resolveDefaultLine();
            $unread = $this->inboxQueryService->totalUnreadCount($line);
        }

        $member = TeamActor::teamMember();
        $items = TeamNavigation::items($member);

        $navItems = collect($items)
            ->map(function (array $item) use ($unread): array {
                if (($item['route'] ?? '') === 'inbox.index' && $unread > 0) {
                    $item['badge'] = $unread > 99 ? '99+' : $unread;
                }

                return $item;
            })
            ->all();

        $view->with('navItems', $navItems);
    }
}
