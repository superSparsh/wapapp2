<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Services;

use App\Domains\Inbox\Support\InboxActor;
use App\Domains\Inbox\Support\InboxInteractivePreview;
use App\Enums\MessageDirection;
use App\Enums\MessageType;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsappLine;
use App\Support\PhoneNormalizer;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InboxExportService
{
    public const CONTENT_MESSAGES = 'messages';

    public const CONTENT_FLOW_RESPONSES = 'flow_responses';

    public const CONTENT_BOTH = 'both';

    public function __construct(
        private readonly InboxSettingsService $settingsService,
    ) {}

    public function exportConversation(Conversation $conversation, string $content = self::CONTENT_BOTH): StreamedResponse
    {
        $conversation->loadMissing('whatsappLine');
        $content = $this->normalizeContent($content);

        $fileName = sprintf(
            'chat-%s-%s.csv',
            $this->sanitizeFilePart($conversation->contact_name ?: 'contact'),
            now()->format('Y-m-d'),
        );

        if ($content === self::CONTENT_FLOW_RESPONSES) {
            $fileName = sprintf(
                'flow-responses-%s-%s.csv',
                $this->sanitizeFilePart($conversation->contact_name ?: 'contact'),
                now()->format('Y-m-d'),
            );
        }

        return $this->streamCsv($fileName, function ($handle) use ($conversation, $content): void {
            $this->writeConversationExport($handle, $conversation, $content);
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
        string $content = self::CONTENT_BOTH,
    ): StreamedResponse {
        $queryService ??= app(InboxQueryService::class);
        $lookbackDays = $lookbackDays ?? (int) config('inbox.default_lookback_days', 7);
        $maxConversations = (int) config('inbox.export_max_conversations', 500);
        $excluded = $this->normalizedPhoneList($excludePhones);
        $content = $this->normalizeContent($content);

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

        $fileName = $content === self::CONTENT_FLOW_RESPONSES
            ? sprintf('inbox-flow-responses-%s.csv', now()->format('Y-m-d-His'))
            : sprintf('inbox-export-%s.csv', now()->format('Y-m-d-His'));

        return $this->streamCsv($fileName, function ($handle) use ($conversations, $content, $from, $to): void {
            if (in_array($content, [self::CONTENT_FLOW_RESPONSES, self::CONTENT_BOTH], true)) {
                $this->writeFlowResponsesTable($handle, $conversations, $from, $to);
            }

            if (in_array($content, [self::CONTENT_MESSAGES, self::CONTENT_BOTH], true)) {
                if ($content === self::CONTENT_BOTH) {
                    fputcsv($handle, ['Chat Messages']);
                    fputcsv($handle, []);
                }

                foreach ($conversations as $conversation) {
                    $this->writeConversationRows($handle, $conversation, includeConversationHeader: true);
                }
            }
        });
    }

    /**
     * @param  resource  $handle
     */
    private function writeConversationExport($handle, Conversation $conversation, string $content): void
    {
        if (in_array($content, [self::CONTENT_FLOW_RESPONSES, self::CONTENT_BOTH], true)) {
            $this->writeFlowResponsesTable($handle, collect([$conversation]));
        }

        if (in_array($content, [self::CONTENT_MESSAGES, self::CONTENT_BOTH], true)) {
            if ($content === self::CONTENT_BOTH) {
                fputcsv($handle, ['Chat Messages']);
                fputcsv($handle, []);
            }

            $this->writeConversationRows($handle, $conversation);
        }
    }

    /**
     * @param  resource  $handle
     * @param  Collection<int, Conversation>  $conversations
     */
    private function writeFlowResponsesTable(
        $handle,
        Collection $conversations,
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
    ): void {
        $rows = [];
        $fieldKeys = [];

        foreach ($conversations as $conversation) {
            foreach ($this->flowSubmissionsForConversation($conversation, $from, $to) as $submission) {
                foreach (array_keys($submission['fields']) as $key) {
                    $fieldKeys[$key] = true;
                }
                $rows[] = $submission;
            }
        }

        fputcsv($handle, ['Flow Form Responses']);
        fputcsv($handle, []);

        if ($rows === []) {
            fputcsv($handle, ['No flow form submissions found in this export.']);
            fputcsv($handle, []);

            return;
        }

        $fieldKeyList = array_keys($fieldKeys);
        $header = array_merge(
            ['Name', 'Phone', 'Flow Name', 'Submitted At'],
            array_map(fn (string $key): string => $this->humanizeFieldKey($key), $fieldKeyList),
        );
        fputcsv($handle, $header);

        foreach ($rows as $row) {
            $line = [
                $row['name'],
                $row['phone'],
                $row['flow_name'],
                $row['submitted_at'],
            ];

            foreach ($fieldKeyList as $key) {
                $line[] = $row['fields'][$key] ?? '';
            }

            fputcsv($handle, $line);
        }

        fputcsv($handle, []);
    }

    /**
     * @return list<array{
     *     name: string,
     *     phone: string,
     *     flow_name: string,
     *     submitted_at: string,
     *     fields: array<string, string>
     * }>
     */
    private function flowSubmissionsForConversation(
        Conversation $conversation,
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
    ): array {
        $messages = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', MessageDirection::Inbound)
            ->where(function ($query): void {
                $query->where('message_type', MessageType::Interactive->value)
                    ->orWhere('metadata->interactive_reply_type', 'nfm_reply');
            })
            ->when($from !== null, fn ($query) => $query->where('created_at', '>=', $from->copy()->startOfDay()))
            ->when($to !== null, fn ($query) => $query->where('created_at', '<=', $to->copy()->endOfDay()))
            ->orderBy('id')
            ->get(['body', 'message_type', 'metadata', 'created_at']);

        $phone = $this->settingsService->shouldMaskPhone($conversation->contact_phone);
        $name = trim((string) ($conversation->contact_name ?? ''));
        if ($name === '') {
            $name = $phone;
        }

        $submissions = [];

        foreach ($messages as $message) {
            $metadata = is_array($message->metadata) ? $message->metadata : [];
            $interactive = isset($metadata['interactive']) && is_array($metadata['interactive'])
                ? $metadata['interactive']
                : null;
            $preview = InboxInteractivePreview::build(
                $interactive,
                (string) ($message->body ?? ''),
                $metadata,
            );
            $flowReply = is_array($preview['flow_reply'] ?? null) ? $preview['flow_reply'] : null;
            if ($flowReply === null) {
                continue;
            }

            $fields = [];
            foreach ($flowReply['fields'] ?? [] as $field) {
                if (! is_array($field)) {
                    continue;
                }
                $key = trim((string) ($field['name'] ?? ''));
                $value = trim((string) ($field['value'] ?? ''));
                if ($key === '' || $value === '') {
                    continue;
                }
                $fields[$key] = $value;
            }

            $submissions[] = [
                'name' => $name,
                'phone' => $phone,
                'flow_name' => trim((string) ($flowReply['flow_name'] ?? '')) ?: 'WhatsApp Flow',
                'submitted_at' => $message->created_at?->toDateTimeString() ?? '',
                'fields' => $fields,
            ];
        }

        return $submissions;
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
            ->get(['body', 'direction', 'message_type', 'status', 'created_at', 'metadata']);

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
                $this->exportBodyForMessage($message),
                $message->created_at?->toDateTimeString(),
            ]);
        }

        fputcsv($handle, []);
    }

    private function exportBodyForMessage(Message $message): string
    {
        $body = (string) ($message->body ?? '');
        $metadata = is_array($message->metadata) ? $message->metadata : [];
        $interactive = isset($metadata['interactive']) && is_array($metadata['interactive'])
            ? $metadata['interactive']
            : null;
        $preview = InboxInteractivePreview::build($interactive, $body, $metadata);
        $flowReply = is_array($preview['flow_reply'] ?? null) ? $preview['flow_reply'] : null;

        if ($flowReply === null) {
            return $body;
        }

        $parts = ['Form submitted'];
        if (! empty($flowReply['flow_name'])) {
            $parts[0] .= ': '.$flowReply['flow_name'];
        }

        foreach ($flowReply['fields'] ?? [] as $field) {
            if (! is_array($field)) {
                continue;
            }
            $label = trim((string) ($field['label'] ?? $field['name'] ?? ''));
            $value = trim((string) ($field['value'] ?? ''));
            if ($label === '' || $value === '') {
                continue;
            }
            $parts[] = $label.': '.$value;
        }

        return implode(' | ', $parts);
    }

    private function humanizeFieldKey(string $key): string
    {
        $leaf = str_contains($key, '.') ? substr($key, (int) strrpos($key, '.') + 1) : $key;
        $leaf = preg_replace('/_\d+$/', '', $leaf) ?? $leaf;
        $leaf = str_replace(['_', '-'], ' ', $leaf);

        return ucwords(trim($leaf));
    }

    private function normalizeContent(string $content): string
    {
        $content = strtolower(trim($content));

        return in_array($content, [
            self::CONTENT_MESSAGES,
            self::CONTENT_FLOW_RESPONSES,
            self::CONTENT_BOTH,
        ], true) ? $content : self::CONTENT_BOTH;
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
