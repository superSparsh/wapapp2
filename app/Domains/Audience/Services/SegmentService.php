<?php

declare(strict_types=1);

namespace App\Domains\Audience\Services;

use App\Domains\Audience\Enums\SegmentConditionType;
use App\Domains\Audience\Models\Segment;
use App\Models\Contact;
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
        $segment = Segment::query()->create($data);
        $this->recalculateCount($segment);

        return $segment;
    }

    /**
     * Update a segment and recalculate count.
     */
    public function update(Segment $segment, array $data): Segment
    {
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
    public function buildQuery(Segment $segment): \Illuminate\Database\Eloquent\Builder
    {
        $query = Contact::query();

        if ($segment->mail_list_id) {
            $query->where('mail_list_id', $segment->mail_list_id);
        }

        $conditions = $segment->conditions ?? [];
        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? null;
            $type = SegmentConditionType::tryFrom($condition['type'] ?? '');
            $value = $condition['value'] ?? null;

            if (! $field || ! $type) {
                continue;
            }

            $type->apply($query, $field, $value);
        }

        return $query;
    }
}
