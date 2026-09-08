<?php

declare(strict_types=1);

namespace App\Domains\TriggerTemplate\Support;

use App\Models\TriggerVariable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TriggerPresenter
{
    /**
     * @return array<int, array{
     *     serial: int,
     *     uuid: string,
     *     variable_name: string,
     *     template_name: string,
     *     template_code: string,
     *     list_name: string,
     *     delete_url: string,
     * }>
     */
    public function tableRows(LengthAwarePaginator $paginator): array
    {
        $offset = ($paginator->currentPage() - 1) * $paginator->perPage();

        return $paginator
            ->getCollection()
            ->values()
            ->map(fn (TriggerVariable $trigger, int $index): array => $this->row($trigger, $offset + $index + 1))
            ->all();
    }

    /**
     * @return array{
     *     serial: int,
     *     uuid: string,
     *     variable_name: string,
     *     template_name: string,
     *     template_code: string,
     *     list_name: string,
     *     delete_url: string,
     * }
     */
    public function row(TriggerVariable $trigger, int $serial): array
    {
        return [
            'serial' => $serial,
            'uuid' => $trigger->uuid,
            'variable_name' => $trigger->variable_name,
            'template_name' => $trigger->template_name,
            'template_code' => $trigger->template_code,
            'list_name' => $trigger->list_name ?? '—',
            'delete_url' => route('trigger-template.destroy', $trigger),
        ];
    }

    /**
     * @param  array<int, array{code: string, name: string, language?: string, category?: string}>  $templates
     * @return array<int, array{code: string, name: string}>
     */
    public function templateOptions(array $templates): array
    {
        return collect($templates)
            ->map(fn (array $template): array => [
                'code' => (string) ($template['code'] ?? ''),
                'name' => (string) ($template['name'] ?? $template['code'] ?? ''),
            ])
            ->filter(fn (array $template): bool => $template['code'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function mailListOptions(Collection $lists): array
    {
        return $lists
            ->map(fn (array $list): array => [
                'id' => (int) ($list['id'] ?? 0),
                'name' => (string) ($list['name'] ?? ''),
            ])
            ->filter(fn (array $list): bool => $list['id'] > 0 && $list['name'] !== '')
            ->values()
            ->all();
    }
}
