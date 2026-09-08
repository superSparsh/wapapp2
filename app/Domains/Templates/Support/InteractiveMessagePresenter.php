<?php

declare(strict_types=1);

namespace App\Domains\Templates\Support;

use App\Models\InteractiveMessage;
use Illuminate\Support\Collection;

class InteractiveMessagePresenter
{
    /**
     * @param  Collection<int, InteractiveMessage>  $messages
     * @return list<array<string, mixed>>
     */
    public function tableRows(Collection $messages): array
    {
        return $messages
            ->values()
            ->map(fn (InteractiveMessage $message, int $index): array => [
                'serial' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                'name' => $message->name,
                'type' => ucfirst(str_replace('_', ' ', $message->type)),
                'type_raw' => $message->type,
                'created_at' => $message->created_at?->format('Y-m-d h:i A') ?? '—',
                'edit_url' => route('templates.free.edit', $message),
                'preview_url' => route('templates.free.preview', $message),
                'delete_url' => route('templates.free.destroy', $message),
            ])
            ->all();
    }
}
