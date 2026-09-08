<?php

declare(strict_types=1);

namespace App\Domains\Templates\Support;

use App\Models\Variable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TemplateVariablePresenter
{
    /**
     * @return list<array<string, mixed>>
     */
    public function tableRows(LengthAwarePaginator $paginator): array
    {
        return collect($paginator->items())
            ->map(fn (Variable $variable, int $index): array => $this->row($variable, $index, (int) $paginator->firstItem()))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function row(Variable $variable, int $offset = 0, int $startIndex = 1): array
    {
        return [
            'serial' => str_pad((string) ($startIndex + $offset), 2, '0', STR_PAD_LEFT),
            'name' => $variable->name,
            'created_at' => $variable->created_at?->format('d-m-Y / h:i A') ?? '—',
            'data_type_label' => $variable->data_type->label(),
            'type_label' => $variable->type->label(),
            'edit_url' => route('templates.variables.edit', $variable),
            'delete_url' => route('templates.variables.destroy', $variable),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formState(?Variable $variable = null): array
    {
        return [
            'name' => old('name', $variable?->name ?? ''),
            'data_type' => old('data_type', $variable?->data_type?->value ?? 'string'),
            'value' => old('value', $variable?->value ?? ''),
        ];
    }
}
