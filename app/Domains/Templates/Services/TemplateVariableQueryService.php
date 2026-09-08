<?php

declare(strict_types=1);

namespace App\Domains\Templates\Services;

use App\Domains\Templates\Support\VariableActorContext;
use App\Models\Variable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class TemplateVariableQueryService
{
    public function __construct(
        private readonly VariableActorContext $actorContext,
    ) {}

    public function paginate(?string $keyword = null, ?int $perPage = null): LengthAwarePaginator
    {
        $perPage = $perPage ?? (int) config('templates.variables_per_page', 10);

        return $this->scopedQuery($keyword)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findByUuid(string $uuid): ?Variable
    {
        return $this->scopedQuery()->where('uuid', $uuid)->first();
    }

    /** @return Builder<Variable> */
    private function scopedQuery(?string $keyword = null): Builder
    {
        $query = Variable::query();

        $lineId = $this->actorContext->whatsappLineId();
        if ($lineId !== null) {
            $query->where('whatsapp_line_id', $lineId);
        } else {
            $query->whereNull('whatsapp_line_id');
        }

        $teamMemberId = $this->actorContext->teamMemberId();
        if ($teamMemberId !== null) {
            $query->where('team_member_id', $teamMemberId);
        }

        $keyword = trim((string) $keyword);
        if ($keyword !== '') {
            $query->where('name', 'like', '%'.$keyword.'%');
        }

        return $query;
    }
}
