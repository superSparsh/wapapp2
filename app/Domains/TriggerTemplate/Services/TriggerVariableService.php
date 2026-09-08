<?php

declare(strict_types=1);

namespace App\Domains\TriggerTemplate\Services;

use App\Models\TriggerVariable;
use Illuminate\Support\Facades\DB;

class TriggerVariableService
{
    /**
     * @param  array{
     *     variable_name: string,
     *     template_code: string,
     *     template_name: string,
     *     whatsapp_line_id?: int|null,
     *     list_id?: int|null,
     *     list_name?: string|null,
     * }  $data
     */
    public function create(array $data): TriggerVariable
    {
        return DB::transaction(fn (): TriggerVariable => TriggerVariable::query()->create([
            'variable_name' => $data['variable_name'],
            'template_code' => $data['template_code'],
            'template_name' => $data['template_name'],
            'whatsapp_line_id' => $data['whatsapp_line_id'] ?? null,
            'list_id' => $data['list_id'] ?? null,
            'list_name' => $data['list_name'] ?? null,
        ]));
    }

    public function delete(TriggerVariable $triggerVariable): void
    {
        $triggerVariable->delete();
    }
}
