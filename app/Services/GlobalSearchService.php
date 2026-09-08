<?php

declare(strict_types=1);

namespace App\Services;

use App\Domains\Inbox\Services\InboxQueryService;
use App\Domains\Inbox\Services\InboxService;
use App\Domains\Team\Support\TeamActor;
use App\Domains\Team\Support\TeamNavigation;
use App\Domains\Team\Support\TeamPermissions;
use App\Models\WhatsappLine;

class GlobalSearchService
{
    public function __construct(
        private readonly InboxQueryService $inboxQueryService,
        private readonly InboxService $inboxService,
    ) {}

    /**
     * @return array{
     *     pages: array<int, array{label: string, group: ?string, url: string}>,
     *     conversations: array<int, array{uuid: string, name: string, phone: string, preview: string, url: string}>
     * }
     */
    public function search(string $query, int $limit = 8): array
    {
        $query = trim($query);

        if ($query === '') {
            return [
                'pages' => [],
                'conversations' => [],
            ];
        }

        return [
            'pages' => $this->searchPages($query, $limit),
            'conversations' => $this->searchConversations($query, $limit),
        ];
    }

    /** @return array<int, array{label: string, group: ?string, url: string}> */
    private function searchPages(string $query, int $limit): array
    {
        $needle = mb_strtolower($query);
        $results = [];

        foreach ($this->navigationItems() as $item) {
            $haystack = mb_strtolower(trim(($item['group'] ?? '').' '.$item['label']));

            if (! str_contains($haystack, $needle)) {
                continue;
            }

            $results[] = $item;

            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    /** @return array<int, array{label: string, group: ?string, url: string}> */
    private function navigationItems(): array
    {
        $items = [];
        $navigation = TeamNavigation::items(TeamActor::teamMember());

        foreach ($navigation as $entry) {
            if (! empty($entry['children'])) {
                foreach ($entry['children'] as $child) {
                    if (! isset($child['route'])) {
                        continue;
                    }

                    $items[] = [
                        'label' => (string) $child['label'],
                        'group' => (string) $entry['label'],
                        'url' => route($child['route']),
                    ];
                }

                continue;
            }

            if (! isset($entry['route'])) {
                continue;
            }

            $items[] = [
                'label' => (string) $entry['label'],
                'group' => null,
                'url' => route($entry['route']),
            ];
        }

        return $items;
    }

    /** @return array<int, array{uuid: string, name: string, phone: string, preview: string, url: string}> */
    private function searchConversations(string $query, int $limit): array
    {
        $member = TeamActor::teamMember();

        if ($member !== null && ! TeamPermissions::isEnabled($member->permissions, 'inbox_read')) {
            return [];
        }

        $line = $this->resolveDefaultLine();

        if ($line === null) {
            return [];
        }

        $threads = $this->inboxQueryService->paginateThreads(
            line: $line,
            search: $query,
            limit: $limit,
        );

        return collect($threads['items'])->map(function (array $thread) use ($query): array {
            return [
                'uuid' => (string) $thread['uuid'],
                'name' => (string) $thread['name'],
                'phone' => (string) ($thread['phone'] ?? ''),
                'preview' => (string) ($thread['preview'] ?? ''),
                'url' => route('inbox.show', [
                    'conversation' => $thread['uuid'],
                    'q' => $query,
                ]),
            ];
        })->all();
    }

    private function resolveDefaultLine(): ?WhatsappLine
    {
        try {
            return $this->inboxService->requireDefaultLine();
        } catch (\Throwable) {
            return null;
        }
    }
}
