<?php

declare(strict_types=1);

namespace App\Domains\WhatsappFlow\Services;

use App\Enums\WhatsappFlowStatus;
use App\Models\WhatsappFlow;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class WhatsappFlowPresenter
{
    /**
     * Transform paginated flows into table-ready rows.
     *
     * @return array<int, array<string, mixed>>
     */
    public function tableRows(LengthAwarePaginator $paginator): array
    {
        $rows = [];
        $serial = ($paginator->currentPage() - 1) * $paginator->perPage() + 1;

        foreach ($paginator->items() as $flow) {
            /** @var WhatsappFlow $flow */
            $rows[] = [
                'serial' => $serial++,
                'name' => $flow->name,
                'status' => $flow->status->value,
                'status_label' => $flow->status->label(),
                'is_active' => $flow->isActive(),
                'submission_count' => $flow->submissions_count ?? 0,
                'screen_count' => $flow->screenCount(),
                'published_at' => $flow->published_at?->format('M d, Y H:i'),
                'meta_flow_id' => $flow->meta_flow_id,
                'data_exchange_endpoint' => $flow->data_exchange_endpoint,
                'show_url' => route('whatsapp-flows.show', $flow),
                'edit_url' => route('whatsapp-flows.edit', $flow),
                'delete_url' => route('whatsapp-flows.destroy', $flow),
                'publish_url' => route('whatsapp-flows.publish', $flow),
                'archive_url' => route('whatsapp-flows.archive', $flow),
                'duplicate_url' => route('whatsapp-flows.duplicate', $flow),
                'stats_url' => route('whatsapp-flows.stats', $flow),
                'preview_url' => route('whatsapp-flows.preview', $flow),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, string>
     */
    public function statusOptions(): array
    {
        $options = ['' => 'All Status'];

        foreach (WhatsappFlowStatus::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }
}
