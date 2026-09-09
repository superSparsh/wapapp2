<?php

declare(strict_types=1);

namespace App\Domains\Admin\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class AdminListQuery
{
    /**
     * @param  list<string>  $allowedSorts
     * @return array{
     *   q: string,
     *   date_from: string,
     *   date_to: string,
     *   sort: string,
     *   direction: string,
     *   filters: array<string, mixed>
     * }
     */
    public static function fromRequest(
        Request $request,
        array $allowedSorts = ['created_at', 'id', 'name'],
        string $defaultSort = 'created_at',
        string $defaultDirection = 'desc',
    ): array {
        $q = trim((string) $request->query('q', $request->query('search', '')));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));
        $sort = (string) $request->query('sort', $defaultSort);
        $direction = strtolower((string) $request->query('direction', $defaultDirection));

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = $defaultSort;
        }

        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = $defaultDirection;
        }

        return [
            'q' => $q,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'sort' => $sort,
            'direction' => $direction,
            'filters' => $request->query(),
        ];
    }

    /**
     * @param  Builder<*>|QueryBuilder  $query
     * @param  list<string>  $columns
     */
    public static function applySearch(Builder|QueryBuilder $query, string $q, array $columns): void
    {
        $q = trim($q);
        if ($q === '' || $columns === []) {
            return;
        }

        $like = '%'.$q.'%';
        $query->where(function ($builder) use ($columns, $like): void {
            foreach ($columns as $index => $column) {
                if ($index === 0) {
                    $builder->where($column, 'like', $like);
                } else {
                    $builder->orWhere($column, 'like', $like);
                }
            }
        });
    }

    /**
     * Date range in IST calendar days → UTC-aware Carbon bounds on a datetime/timestamp column.
     *
     * @param  Builder<*>|QueryBuilder  $query
     */
    public static function applyDateRange(
        Builder|QueryBuilder $query,
        string $column,
        string $dateFrom,
        string $dateTo,
        bool $columnIsUnix = false,
    ): void {
        $tz = ist_timezone();

        if ($dateFrom !== '') {
            $from = Carbon::parse($dateFrom, $tz)->startOfDay();
            if ($columnIsUnix) {
                $query->where($column, '>=', $from->timestamp);
            } else {
                $query->where($column, '>=', $from);
            }
        }

        if ($dateTo !== '') {
            $to = Carbon::parse($dateTo, $tz)->endOfDay();
            if ($columnIsUnix) {
                $query->where($column, '<=', $to->timestamp);
            } else {
                $query->where($column, '<=', $to);
            }
        }
    }

    /**
     * @param  Builder<*>|QueryBuilder  $query
     * @param  array<string, string>  $map  request sort key => actual column
     */
    public static function applySort(
        Builder|QueryBuilder $query,
        string $sort,
        string $direction,
        array $map,
        string $fallback = 'id',
    ): void {
        $column = $map[$sort] ?? $fallback;
        $dir = strtolower($direction) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($column, $dir);
    }
}
