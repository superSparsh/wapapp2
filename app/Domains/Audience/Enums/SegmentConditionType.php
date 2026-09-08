<?php

declare(strict_types=1);

namespace App\Domains\Audience\Enums;

enum SegmentConditionType: string
{
    case Equals = 'equals';
    case NotEquals = 'not_equals';
    case Contains = 'contains';
    case StartsWith = 'starts_with';
    case EndsWith = 'ends_with';
    case GreaterThan = 'greater_than';
    case LessThan = 'less_than';
    case IsEmpty = 'is_empty';
    case IsNotEmpty = 'is_not_empty';

    /**
     * Apply this condition as a query scope.
     */
    public function apply(\Illuminate\Database\Eloquent\Builder $query, string $column, mixed $value): \Illuminate\Database\Eloquent\Builder
    {
        return match ($this) {
            self::Equals => $query->where($column, $value),
            self::NotEquals => $query->where($column, '!=', $value),
            self::Contains => $query->where($column, 'LIKE', "%{$value}%"),
            self::StartsWith => $query->where($column, 'LIKE', "{$value}%"),
            self::EndsWith => $query->where($column, 'LIKE', "%{$value}"),
            self::GreaterThan => $query->where($column, '>', $value),
            self::LessThan => $query->where($column, '<', $value),
            self::IsEmpty => $query->where(function ($q) use ($column): void {
                $q->whereNull($column)->orWhere($column, '');
            }),
            self::IsNotEmpty => $query->where(function ($q) use ($column): void {
                $q->whereNotNull($column)->where($column, '!=', '');
            }),
        };
    }
}
