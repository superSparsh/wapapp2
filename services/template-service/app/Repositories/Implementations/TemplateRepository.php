<?php

declare(strict_types=1);

namespace App\Repositories\Implementations;

use App\Enums\TemplateSource;
use App\Enums\TemplateStatus;
use App\Models\Template;
use App\Repositories\Interfaces\TemplateRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TemplateRepository implements TemplateRepositoryInterface
{
    public function paginate(
        ?string $keyword = null,
        ?string $category = null,
        ?string $type = null,
        bool $approvedOnly = false,
        ?int $whatsappLineId = null,
        int $perPage = 10,
        int $page = 1,
    ): LengthAwarePaginator {
        return $this->buildQuery($keyword, $category, $type, $approvedOnly, $whatsappLineId)
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function list(
        ?string $keyword = null,
        ?string $category = null,
        ?string $type = null,
        bool $approvedOnly = false,
        ?int $whatsappLineId = null,
    ): Collection {
        return $this->buildQuery($keyword, $category, $type, $approvedOnly, $whatsappLineId)->get();
    }

    public function findById(int $id): ?Template
    {
        return Template::query()->find($id);
    }

    public function findByUuid(string $uuid): ?Template
    {
        return Template::query()->where('uuid', $uuid)->first();
    }

    public function findByCode(string $code): ?Template
    {
        return Template::query()->where('code', $code)->first();
    }

    public function create(array $attributes): Template
    {
        return Template::query()->create($attributes);
    }

    public function update(Template $template, array $attributes): Template
    {
        $template->update($attributes);

        return $template->refresh();
    }

    public function delete(Template $template): bool
    {
        return (bool) $template->delete();
    }

    public function bulkDelete(array $uuids): int
    {
        $templates = Template::query()->whereIn('uuid', $uuids)->get();
        $count = 0;
        foreach ($templates as $template) {
            if ($template->delete()) {
                $count++;
            }
        }

        return $count;
    }

    public function approvedOptions(?int $whatsappLineId = null): array
    {
        $query = Template::query()->where('status', TemplateStatus::Approved);

        if ($whatsappLineId !== null) {
            $query->where(function (Builder $builder) use ($whatsappLineId): void {
                $builder->where('whatsapp_line_id', $whatsappLineId)
                    ->orWhereNull('whatsapp_line_id');
            });
        }

        return $query->orderBy('name')
            ->get(['code', 'name', 'language', 'category'])
            ->map(fn (Template $template): array => [
                'code' => (string) $template->code,
                'name' => $template->name,
                'language' => $template->language,
                'category' => $template->category,
            ])
            ->filter(fn (array $row): bool => $row['code'] !== '')
            ->values()
            ->all();
    }

    private function buildQuery(
        ?string $keyword = null,
        ?string $category = null,
        ?string $type = null,
        bool $approvedOnly = false,
        ?int $whatsappLineId = null,
    ): Builder {
        $statuses = $approvedOnly
            ? [TemplateStatus::Approved]
            : [
                TemplateStatus::Approved,
                TemplateStatus::Draft,
                TemplateStatus::PendingReview,
                TemplateStatus::Rejected,
            ];

        $query = Template::query()
            ->when($whatsappLineId !== null, fn ($builder) => $builder->where(function ($scoped) use ($whatsappLineId) {
                $scoped->where('whatsapp_line_id', $whatsappLineId)
                    ->orWhereNull('whatsapp_line_id');
            }))
            ->whereIn('status', $statuses)
            ->orderByDesc('updated_at');

        $keyword = trim((string) $keyword);
        if ($keyword !== '') {
            $query->where(function ($builder) use ($keyword) {
                $builder->where('name', 'like', '%'.$keyword.'%')
                    ->orWhere('code', 'like', '%'.$keyword.'%');
            });
        }

        $category = trim((string) $category);
        if ($category !== '') {
            $query->where('category', $category);
        }

        $type = trim((string) $type);
        if ($type === 'Regular') {
            $query->where('source', TemplateSource::Cams);
        } elseif ($type === 'Draft') {
            $query->where('source', TemplateSource::Local);
        }

        return $query;
    }
}
