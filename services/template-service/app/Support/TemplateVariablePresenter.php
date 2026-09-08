<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Variable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
            'id' => $variable->id,
            'uuid' => $variable->uuid,
            'name' => $variable->name,
            'created_at' => $variable->created_at?->format('d-m-Y / h:i A') ?? '—',
            'data_type' => $variable->data_type->value,
            'data_type_label' => $variable->data_type->label(),
            'type' => $variable->type->value,
            'type_label' => $variable->type->label(),
            'value' => $variable->value,
        ];
    }
}
