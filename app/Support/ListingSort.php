<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;

/**
 * Shared GET sort/direction helpers for tenant listing pages.
 * Pairs with x-ui.listing-toolbar (sort + direction query params).
 */
final class ListingSort
{
    /**
     * @param  list<string>  $allowedSorts
     * @return array{sort: string, direction: string}
     */
    public static function fromRequest(
        Request $request,
        array $allowedSorts = ['created_at', 'name'],
        string $defaultSort = 'created_at',
        string $defaultDirection = 'desc',
    ): array {
        $sort = (string) $request->query('sort', $request->query('sort_by', $defaultSort));
        $direction = strtolower((string) $request->query(
            'direction',
            $request->query('sort_dir', $defaultDirection),
        ));

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = $defaultSort;
        }

        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = $defaultDirection;
        }

        return [
            'sort' => $sort,
            'direction' => $direction,
        ];
    }

    /**
     * @param  Builder<*>|QueryBuilder  $query
     * @param  array<string, string>  $map  request sort key => DB column
     */
    public static function apply(
        Builder|QueryBuilder $query,
        string $sort,
        string $direction,
        array $map,
        string $fallback = 'id',
    ): void {
        $column = $map[$sort] ?? $fallback;
        $dir = strtolower($direction) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($column, $dir);

        // Keep listing order deterministic when primary values collide.
        if ($column !== 'id') {
            $query->orderBy('id', $dir);
        }
    }

    /**
     * Common newest / oldest / name options for listing toolbars.
     *
     * @return list<array{value: string, label: string, direction: string}>
     */
    public static function defaultOptions(
        bool $includeName = true,
        bool $includeUpdated = false,
    ): array {
        $options = [
            ['value' => 'created_at', 'label' => 'Newest first', 'direction' => 'desc'],
            ['value' => 'created_at', 'label' => 'Oldest first', 'direction' => 'asc'],
        ];

        if ($includeUpdated) {
            $options[] = ['value' => 'updated_at', 'label' => 'Recently updated', 'direction' => 'desc'];
        }

        if ($includeName) {
            $options[] = ['value' => 'name', 'label' => 'Name A–Z', 'direction' => 'asc'];
            $options[] = ['value' => 'name', 'label' => 'Name Z–A', 'direction' => 'desc'];
        }

        return $options;
    }
}
