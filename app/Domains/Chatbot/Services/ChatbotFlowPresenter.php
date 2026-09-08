<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Services;

use App\Enums\ChatbotFlowStatus;
use App\Models\ChatbotFlow;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ChatbotFlowPresenter
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function tableRows(LengthAwarePaginator $paginator): array
    {
        $offset = ($paginator->currentPage() - 1) * $paginator->perPage();

        return $paginator
            ->getCollection()
            ->values()
            ->map(fn (ChatbotFlow $flow, int $index): array => $this->row($flow, $offset + $index + 1))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function row(ChatbotFlow $flow, int $serial): array
    {
        return [
            'serial' => $serial,
            'uuid' => $flow->uuid,
            'id' => $flow->id,
            'name' => $flow->name,
            'status' => $flow->status->value,
            'status_label' => $this->statusLabel($flow->status),
            'node_count' => $flow->nodeCount(),
            'published_at' => $flow->published_at?->format('M d, Y H:i'),
            'is_active' => $flow->isActive(),
            'edit_url' => route('chatbot.edit', $flow),
            'toggle_url' => route('chatbot.toggle', $flow),
            'publish_url' => route('chatbot.publish', $flow),
            'duplicate_url' => route('chatbot.duplicate', $flow),
            'delete_url' => route('chatbot.destroy', $flow),
        ];
    }

    private function statusLabel(ChatbotFlowStatus $status): string
    {
        return match ($status) {
            ChatbotFlowStatus::Active => 'Active',
            ChatbotFlowStatus::Inactive => 'Inactive',
            ChatbotFlowStatus::Draft => 'Draft',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function statusOptions(): array
    {
        return collect(ChatbotFlowStatus::cases())
            ->map(fn (ChatbotFlowStatus $s): array => [
                'value' => $s->value,
                'label' => $this->statusLabel($s),
            ])
            ->all();
    }
}
