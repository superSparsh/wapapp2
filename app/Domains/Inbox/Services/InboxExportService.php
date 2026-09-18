<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Services;

use App\Domains\Inbox\Support\InboxActor;
use App\Enums\MessageDirection;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsappLine;
use App\Support\PhoneNormalizer;
use Carbon\CarbonInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InboxExportService
{
    public function __construct(
        private readonly InboxSettingsService $settingsService,
    ) {}

    public function exportConversation(Conversation $conversation): StreamedResponse
    {
        $conversation->loadMissing('whatsappLine');

        $fileName = sprintf(
            'chat-%s-%s.csv',
            $this->sanitizeFilePart($conversation->contact_name ?: 'contact'),
            now()->format('Y-m-d'),
        );

        return $this->streamCsv($fileName, function ($handle) use ($conversation): void {
            $this->writeConversationRows($handle, $conversation);
        });
    }

    /**
     * @param  array<int, string>  $excludePhones
     */
    public function exportFilteredThreads(
        WhatsappLine $line,
        ?string $search = null,
        bool $unreadOnly = false,
        ?int $lookbackDays = null,
        ?string $scope = null,
        ?array $assigneeFilter = null,
        ?InboxQueryService $queryService = null,
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
        array $excludePhones = [],
    ): StreamedResponse {
        $queryService ??= app(InboxQueryService::class);
        $lookbackDays = $lookbackDays ?? (int) config('inbox.default_lookback_days', 7);
        $maxConversations = (int) config('inbox.export_max_conversations', 500);
        $excluded = $this->normalizedPhoneList($excludePhones);

        $conversations = Conversation::query()
            ->where('whatsapp_line_id', $line->id)
            ->when(
                $from !== null || $to !== null,
                function ($query) use ($from, $to): void {
                    if ($from !== null) {
                        $query->where('last_message_at', '>=', $from->copy()->startOfDay());
                    }
                    if ($to !== null) {
                        $query->where('last_message_at', '<=', $to->copy()->endOfDay());
                    }
                },
                fn ($query) => $query->where('last_message_at', '>=', now()->subDays($lookbackDays)),
            )
            ->when($excluded !== [], function ($query) use ($excluded): void {
                $query->whereNotIn('contact_phone', $excluded);
            })
            ->when($unreadOnly || $scope === 'unread', fn ($query) => $query->where('unread_count', '>', 0))
            ->when($scope === 'mine', function ($query): void {
                $userId = InboxActor::userId();
                $teamMemberId = InboxActor::teamMemberId();

                $query->where(function ($builder) use ($userId, $teamMemberId): void {
                    if ($userId !== null && $teamMemberId !== null) {
                        $builder
                            ->where('assigned_user_id', $userId)
                            ->orWhere('assigned_team_member_id', $teamMemberId);

                        return;
                    }

                    if ($userId !== null) {
                        $builder->where('assigned_user_id', $userId);

                        return;
                    }

                    if ($teamMemberId !== null) {
                        $builder->where('assigned_team_member_id', $teamMemberId);
                    }
                });
            })
            ->when($assigneeFilter !== null, function ($query) use ($assigneeFilter): void {
                if (! empty($assigneeFilter['unassigned'])) {
                    $query->whereNull('assigned_user_id')->whereNull('assigned_team_member_id');
                } elseif (isset($assigneeFilter['user_id'])) {
                    $query->where('assigned_user_id', $assigneeFilter['user_id']);
                } elseif (isset($assigneeFilter['team_member_id'])) {
                    $query->where('assigned_team_member_id', $assigneeFilter['team_member_id']);
                }
            })
            ->when(filled($search), function ($query) use ($search): void {
                $search = trim((string) $search);
                $query->where(function ($builder) use ($search): void {
                    $builder->where('contact_name', 'like', '%'.$search.'%')
                        ->orWhere('contact_phone', 'like', '%'.$search.'%');
                });
            })
            ->orderByDesc('last_message_at')
            ->limit($maxConversations)
            ->get();

        $fileName = sprintf('inbox-export-%s.csv', now()->format('Y-m-d-His'));

        return $this->streamCsv($fileName, function ($handle) use ($conversations): void {
            foreach ($conversations as $conversation) {
                $this->writeConversationRows($handle, $conversation, includeConversationHeader: true);
            }
        });
    }

    /**
     * @param  resource  $handle
     */
    private function writeConversationRows($handle, Conversation $conversation, bool $includeConversationHeader = false): void
    {
        if ($includeConversationHeader) {
            fputcsv($handle, [
                'Conversation',
                $conversation->contact_name,
                $this->settingsService->shouldMaskPhone($conversation->contact_phone),
            ]);
        }

        $messages = Message::query()
            ->where('conversation_id', $conversation->id)
            ->orderBy('id')
            ->get(['body', 'direction', 'message_type', 'status', 'created_at']);

        if ($messages->isEmpty()) {
            return;
        }

        fputcsv($handle, ['Contact', 'Phone', 'Direction', 'Type', 'Status', 'Body', 'Sent At']);

        foreach ($messages as $message) {
            fputcsv($handle, [
                $conversation->contact_name,
                $this->settingsService->shouldMaskPhone($conversation->contact_phone),
                $message->direction === MessageDirection::Outbound ? 'outbound' : 'inbound',
                $message->message_type->value,
                $message->status->value,
                (string) $message->body,
                $message->created_at?->toDateTimeString(),
            ]);
        }

        fputcsv($handle, []);
    }

    /**
     * @param  callable(resource): void  $writer
     */
    private function streamCsv(string $fileName, callable $writer): StreamedResponse
    {
        return response()->streamDownload(function () use ($writer): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            $writer($handle);
            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function sanitizeFilePart(string $value): string
    {
        $value = preg_replace('/[^a-zA-Z0-9_-]+/', '-', strtolower($value)) ?? 'contact';

        return trim($value, '-') ?: 'contact';
    }

    /**
     * @param  array<int, string>  $phones
     * @return array<int, string>
     */
    private function normalizedPhoneList(array $phones): array
    {
        $normalized = [];

        foreach ($phones as $phone) {
            $value = PhoneNormalizer::normalize((string) $phone)
                ?? (preg_replace('/\D+/', '', (string) $phone) ?: '');

            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }
}
