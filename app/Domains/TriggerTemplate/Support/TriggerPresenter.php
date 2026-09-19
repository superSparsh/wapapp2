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
     * @param  array<int, array{code: string, name: string, language?: string, category?: string, preview?: array<string, mixed>}>  $templates
     * @return array<int, array{code: string, name: string, body: string, preview: array{body: string, footer: string, header_type: string, header_text: string, header_image: ?string, header_video: ?string, buttons: array<int, mixed>}}>
     */
    public function templateOptions(array $templates): array
    {
        return collect($templates)
            ->map(function (array $template): array {
                $preview = is_array($template['preview'] ?? null) ? $template['preview'] : [];
                $body = trim((string) ($preview['body'] ?? $template['body_preview'] ?? ''));

                return [
                    'code' => (string) ($template['code'] ?? ''),
                    'name' => (string) ($template['name'] ?? $template['code'] ?? ''),
                    'body' => $body,
                    'preview' => [
                        'body' => $body,
                        'footer' => (string) ($preview['footer'] ?? ''),
                        'header_type' => (string) ($preview['header_type'] ?? 'none'),
                        'header_text' => (string) ($preview['header_text'] ?? ''),
                        'header_image' => $preview['header_image'] ?? null,
                        'header_video' => $preview['header_video'] ?? null,
                        'buttons' => is_array($preview['buttons'] ?? null) ? $preview['buttons'] : [],
                    ],
                ];
            })
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
