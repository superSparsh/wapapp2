<?php

declare(strict_types=1);

namespace App\Domains\Audience\Services;

use App\Domains\Audience\Enums\SegmentConditionType;
use App\Domains\Audience\Models\Segment;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class SegmentService
{
    /**
     * Paginated segments for a mail list.
     */
    public function index(
        ?int $mailListId = null,
        ?string $search = null,
        string $sort = 'created_at',
        string $direction = 'desc',
        int $perPage = 15,
    ): LengthAwarePaginator {
        $allowedSorts = ['created_at', 'name', 'contact_count'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';

        return Segment::query()
            ->when($mailListId, fn ($q) => $q->where('mail_list_id', $mailListId))
            ->when($search, fn ($q) => $q->where('name', 'LIKE', "%{$search}%"))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Create a new segment and calculate contact count.
     */
    public function store(array $data): Segment
    {
        $data['conditions'] = $this->normalizeStoredConditions(
            $data['conditions'] ?? [],
            $data['match_type'] ?? null,
        );
        unset($data['match_type']);

        $segment = Segment::query()->create($data);
        $this->recalculateCount($segment);

        return $segment;
    }

    /**
     * Update a segment and recalculate count.
     */
    public function update(Segment $segment, array $data): Segment
    {
        if (array_key_exists('conditions', $data) || array_key_exists('match_type', $data)) {
            $data['conditions'] = $this->normalizeStoredConditions(
                $data['conditions'] ?? $segment->conditions ?? [],
                $data['match_type'] ?? null,
            );
        }
        unset($data['match_type']);

        $segment->update($data);
        $this->recalculateCount($segment);

        return $segment->fresh();
    }

    /**
     * Delete a segment.
     */
    public function destroy(Segment $segment): void
    {
        $segment->delete();
    }

    /**
     * Recalculate the contact count for a segment based on its conditions.
     */
    public function recalculateCount(Segment $segment): int
    {
        $count = $this->buildQuery($segment)->count();
        $segment->update(['contact_count' => $count]);

        return $count;
    }

    /**
     * Build a query from segment conditions.
     */
    public function buildQuery(Segment $segment): Builder
    {
        $query = Contact::query();

        if ($segment->mail_list_id) {
            $query->where('mail_list_id', $segment->mail_list_id);
        }

        [$match, $rules] = $this->extractMatchAndRules($segment->conditions ?? []);

        if ($rules === []) {
            return $query;
        }

        $applyRules = function (Builder $builder) use ($rules, $match): void {
            foreach ($rules as $index => $condition) {
                $rawField = trim((string) ($condition['field'] ?? ''));
                $type = SegmentConditionType::tryFrom((string) ($condition['type'] ?? ''));
                $value = $condition['value'] ?? null;

                if ($rawField === '' || ! $type) {
                    continue;
                }

                $method = ($match === 'any' && $index > 0) ? 'orWhere' : 'where';
                $namePart = $this->namePartFromField($rawField);

                $builder->{$method}(function (Builder $inner) use ($rawField, $type, $value, $namePart): void {
                    if ($namePart !== null) {
                        $this->applyFirstOrLastNameCondition($inner, $namePart, $type, $value);

                        return;
                    }

                    $field = $this->resolveColumn($rawField);
                    if ($field === '') {
                        return;
                    }

                    $type->apply($inner, $field, $value);
                });
            }
        };

        if ($match === 'any') {
            $query->where(function (Builder $q) use ($applyRules): void {
                $applyRules($q);
            });
        } else {
            $applyRules($query);
        }

        return $query;
    }

    /**
     * @param  array<int|string, mixed>  $conditions
     * @return array{0: string, 1: list<array{field?: string, type?: string, value?: mixed}>}
     */
    public function extractMatchAndRules(array $conditions): array
    {
        if (isset($conditions['rules']) && is_array($conditions['rules'])) {
            $match = (($conditions['match'] ?? 'all') === 'any') ? 'any' : 'all';

            return [$match, array_values($conditions['rules'])];
        }

        return ['all', array_values($conditions)];
    }

    /**
     * @param  array<int|string, mixed>  $conditions
     * @return array{match: string, rules: list<array{field: string, type: string, value: mixed}>}
     */
    private function normalizeStoredConditions(array $conditions, ?string $matchType): array
    {
        [$existingMatch, $rules] = $this->extractMatchAndRules($conditions);
        $match = in_array($matchType, ['all', 'any'], true) ? $matchType : $existingMatch;

        $cleanRules = [];
        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $field = trim((string) ($rule['field'] ?? ''));
            $type = trim((string) ($rule['type'] ?? ''));
            if ($field === '' || $type === '') {
                continue;
            }

            $cleanRules[] = [
                'field' => $field,
                'type' => $type,
                'value' => $rule['value'] ?? null,
            ];
        }

        return [
            'match' => $match,
            'rules' => $cleanRules,
        ];
    }

    private function resolveColumn(string $field): string
    {
        $field = trim($field);
        if ($field === '') {
            return '';
        }

        // First/Last Name are handled separately (custom_fields + name fallback).
        if ($this->namePartFromField($field) !== null) {
            return '';
        }

        $map = [
            'phone_number' => 'phone',
            'whatsapp_number' => 'phone',
        ];

        $column = $map[$field] ?? $field;
        $allowed = ['phone', 'name', 'email', 'country_code', 'status', 'source', 'created_at', 'updated_at'];

        if (in_array($column, $allowed, true)) {
            return $column;
        }

        // Custom list-field tags live in JSON custom_fields.
        return 'custom_fields->'.$column;
    }

    /**
     * @return 'first'|'last'|null
     */
    private function namePartFromField(string $field): ?string
    {
        $normalized = strtoupper(str_replace([' ', '-'], '_', trim($field)));

        return match ($normalized) {
            'FIRST_NAME', 'FIRSTNAME' => 'first',
            'LAST_NAME', 'LASTNAME' => 'last',
            default => null,
        };
    }

    /**
     * Match FIRST_NAME / LAST_NAME against custom_fields and a name-token fallback.
     *
     * @param  'first'|'last'  $part
     */
    private function applyFirstOrLastNameCondition(
        Builder $query,
        string $part,
        SegmentConditionType $type,
        mixed $value,
    ): void {
        $customColumn = 'custom_fields->'.($part === 'first' ? 'FIRST_NAME' : 'LAST_NAME');

        $query->where(function (Builder $outer) use ($customColumn, $part, $type, $value): void {
            $type->apply($outer, $customColumn, $value);

            $outer->orWhere(function (Builder $nameQuery) use ($part, $type, $value): void {
                $this->applyNameTokenFallback($nameQuery, $part, $type, $value);
            });
        });
    }

    /**
     * @param  'first'|'last'  $part
     */
    private function applyNameTokenFallback(
        Builder $query,
        string $part,
        SegmentConditionType $type,
        mixed $value,
    ): void {
        $raw = trim((string) ($value ?? ''));

        match ($type) {
            SegmentConditionType::IsEmpty => $query->where(function (Builder $q): void {
                $q->whereNull('name')->orWhere('name', '');
            }),
            SegmentConditionType::IsNotEmpty => $query->where(function (Builder $q): void {
                $q->whereNotNull('name')->where('name', '!=', '');
            }),
            SegmentConditionType::Equals => $part === 'first'
                ? $query->where(function (Builder $q) use ($raw): void {
                    $q->where('name', $raw)->orWhere('name', 'LIKE', $raw.' %');
                })
                : $query->where(function (Builder $q) use ($raw): void {
                    $q->where('name', $raw)->orWhere('name', 'LIKE', '% '.$raw);
                }),
            SegmentConditionType::NotEquals => $part === 'first'
                ? $query->where(function (Builder $q) use ($raw): void {
                    $q->where(function (Builder $inner) use ($raw): void {
                        $inner->whereNull('name')
                            ->orWhere(function (Builder $n) use ($raw): void {
                                $n->where('name', '!=', $raw)
                                    ->where('name', 'NOT LIKE', $raw.' %');
                            });
                    });
                })
                : $query->where(function (Builder $q) use ($raw): void {
                    $q->where(function (Builder $inner) use ($raw): void {
                        $inner->whereNull('name')
                            ->orWhere(function (Builder $n) use ($raw): void {
                                $n->where('name', '!=', $raw)
                                    ->where('name', 'NOT LIKE', '% '.$raw);
                            });
                    });
                }),
            SegmentConditionType::Contains => $query->where('name', 'LIKE', '%'.$raw.'%'),
            SegmentConditionType::StartsWith => $part === 'first'
                ? $query->where('name', 'LIKE', $raw.'%')
                : $query->where('name', 'LIKE', '% '.$raw.'%'),
            SegmentConditionType::EndsWith => $part === 'last'
                ? $query->where(function (Builder $q) use ($raw): void {
                    $q->where('name', 'LIKE', '%'.$raw)
                        ->orWhere('name', $raw);
                })
                : $query->where(function (Builder $q) use ($raw): void {
                    $q->where('name', 'LIKE', $raw.' %')
                        ->orWhere('name', $raw);
                }),
            SegmentConditionType::GreaterThan => $query->where('name', '>', $raw),
            SegmentConditionType::LessThan => $query->where('name', '<', $raw),
        };
    }
}
