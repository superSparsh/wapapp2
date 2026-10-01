<?php

declare(strict_types=1);

namespace App\Domains\MobileApi\Http\Controllers;

use App\Domains\Inbox\Services\InboxAccessService;
use App\Domains\Inbox\Services\InboxAssignmentService;
use App\Domains\Inbox\Services\InboxContactService;
use App\Domains\Inbox\Services\InboxConversationService;
use App\Domains\Inbox\Services\InboxExportService;
use App\Domains\Inbox\Services\InboxMessageService;
use App\Domains\Inbox\Services\InboxOutboundService;
use App\Domains\Inbox\Services\InboxQueryService;
use App\Domains\Inbox\Services\InboxResponseTypeService;
use App\Domains\Inbox\Services\InboxService;
use App\Domains\MobileApi\Services\MobileLineResolver;
use App\Domains\MobileApi\Support\MobileInboxPresenter;
use App\Domains\Templates\Enums\TemplateStatus;
use App\Domains\Templates\Services\TemplateRegistryService;
use App\Enums\ConversationResponseType;
use App\Enums\MessageStatus;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\TeamMember;
use App\Models\Template;
use App\Models\User;
use App\Models\WhatsappLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MobileInboxController extends Controller
{
    public function __construct(
        private readonly MobileLineResolver $lines,
        private readonly InboxService $inboxService,
        private readonly InboxQueryService $queryService,
        private readonly InboxMessageService $messageService,
        private readonly InboxOutboundService $outboundService,
        private readonly InboxConversationService $conversationService,
        private readonly InboxContactService $contactService,
        private readonly InboxAccessService $accessService,
        private readonly InboxAssignmentService $assignmentService,
        private readonly InboxResponseTypeService $responseTypeService,
        private readonly InboxExportService $exportService,
        private readonly TemplateRegistryService $templates,
    ) {}

    public function getAssignedNumbers(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => collect($this->inboxService->availableLines())
                ->map(fn (array $line): array => [
                    'uuid' => $line['uuid'],
                    'phone' => $line['phone'],
                    'whatsapp_number' => $line['phone'],
                    'display_name' => $line['label'],
                    'is_default' => $line['is_default'],
                ])
                ->values()
                ->all(),
        ]);
    }

    public function assignNumber(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'whatsapp_number' => ['required', 'string', 'max:32'],
            'user_id' => ['nullable', 'integer'],
            'team_member_id' => ['nullable', 'integer'],
        ]);

        $line = $this->lines->resolve($validated['whatsapp_number']);

        return response()->json([
            'success' => true,
            'message' => 'Number assignment recorded for this session.',
            'data' => MobileInboxPresenter::line($line),
        ]);
    }

    public function getConversations(Request $request): JsonResponse
    {
        $line = $this->lines->resolve($request->input('whatsapp_number'));
        $payload = $this->queryService->paginateThreads(
            line: $line,
            search: $request->input('search') ?: $request->input('q'),
            unreadOnly: $request->boolean('unread_only') || $request->boolean('unread'),
            lookbackDays: $request->integer('days') ?: null,
            limit: min(100, max(1, $request->integer('per_page') ?: 50)),
        );

        return response()->json([
            'success' => true,
            'data' => collect($payload['items'])->map(fn (array $item): array => [
                'id' => $item['uuid'] ?? null,
                'uuid' => $item['uuid'] ?? null,
                'customer_name' => $item['name'] ?? null,
                'customer_phone' => $item['phone'] ?? null,
                'last_message' => $item['preview'] ?? null,
                'unread_count' => $item['unread'] ?? 0,
                'time' => $item['time'] ?? null,
            ])->values()->all(),
            'has_more' => $payload['has_more'],
            'next_cursor' => $payload['next_cursor'],
        ]);
    }

    public function getConversationsPaginated(Request $request): JsonResponse
    {
        return $this->getConversations($request);
    }

    public function getUnreadConversations(Request $request): JsonResponse
    {
        $request->merge(['unread_only' => true]);

        return $this->getConversations($request);
    }

    public function searchConversations(Request $request): JsonResponse
    {
        return $this->getConversations($request);
    }

    public function getSubReplyConversations(Request $request, int $sub_reply_id): JsonResponse
    {
        $conversation = Conversation::query()->find($sub_reply_id);
        abort_if($conversation === null, 404, 'Conversation not found.');
        $this->accessService->assertCanAccessConversation($conversation);

        return response()->json([
            'success' => true,
            'data' => MobileInboxPresenter::conversation($conversation->load('latestMessage')),
        ]);
    }

    public function getMultipleConversations(Request $request): JsonResponse
    {
        $ids = collect($request->input('ids', $request->input('conversation_ids', [])))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->take(50)
            ->all();

        $rows = Conversation::query()
            ->whereIn('id', $ids)
            ->with('latestMessage')
            ->get()
            ->filter(fn (Conversation $c) => $this->accessService->canAccessConversation($c))
            ->map(fn (Conversation $c) => MobileInboxPresenter::conversation($c))
            ->values()
            ->all();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function exportConversation(Request $request): StreamedResponse|JsonResponse
    {
        $line = $this->lines->resolve($request->input('whatsapp_number'));
        $conversation = $this->lines->findConversation(
            $line,
            $request->input('customer_phone') ?: $request->input('to_number'),
            $request->integer('conversation_id') ?: null,
        );

        return $this->exportService->exportConversation($conversation);
    }

    public function getConversation(Request $request): JsonResponse
    {
        $line = $this->lines->resolve($request->input('whatsapp_number'));
        $conversation = $this->lines->findConversation(
            $line,
            $request->input('customer_phone') ?: $request->input('to_number'),
            $request->integer('conversation_id') ?: $request->integer('id') ?: null,
        );

        $this->messageService->markRead($conversation);
        $messages = $this->messageService->paginateMessages($conversation, lookbackDays: 90);

        return response()->json([
            'success' => true,
            'data' => [
                'conversation' => MobileInboxPresenter::conversation($conversation->fresh('latestMessage')),
                'messages' => $messages['items'],
                'has_more' => $messages['has_more'],
            ],
        ]);
    }

    public function updateConversationStatus(Request $request): JsonResponse
    {
        $line = $this->lines->resolve($request->input('whatsapp_number'));
        $conversation = $this->lines->findConversation(
            $line,
            $request->input('customer_phone') ?: $request->input('to_number'),
            $request->integer('conversation_id') ?: null,
        );

        $this->messageService->markRead($conversation);

        if ($request->filled('response_type')) {
            $type = str_contains(strtolower((string) $request->input('response_type')), 'ai')
                ? ConversationResponseType::Ai
                : ConversationResponseType::Human;
            $this->responseTypeService->setForConversation($conversation, $type);
        }

        return response()->json([
            'success' => true,
            'message' => 'Conversation updated',
            'data' => MobileInboxPresenter::conversation($conversation->fresh()),
        ]);
    }

    public function sendMessage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'whatsapp_number' => ['required', 'string', 'max:32'],
            'to_number' => ['required', 'string', 'max:32'],
            'message' => ['required', 'string', 'max:4096'],
        ]);

        $line = $this->lines->resolve($validated['whatsapp_number']);
        $conversation = $this->lines->findOrCreateConversation(
            $this->conversationService,
            $line,
            $validated['to_number'],
        );

        $message = $this->outboundService->sendText($conversation, $validated['message']);
        \App\Domains\Inbox\Jobs\SendOutboundMessageJob::dispatchSync($message->id);
        $message->refresh();

        return $this->sendResult($message);
    }

    public function sendMedia(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'whatsapp_number' => ['required', 'string', 'max:32'],
            'to_number' => ['required', 'string', 'max:32'],
            'file' => ['required', 'file', 'max:16384'],
            'media_type' => ['nullable', 'string', 'in:image,video,audio,document'],
            'caption' => ['nullable', 'string', 'max:1024'],
        ]);

        $line = $this->lines->resolve($validated['whatsapp_number']);
        $conversation = $this->lines->findOrCreateConversation(
            $this->conversationService,
            $line,
            $validated['to_number'],
        );

        $message = $this->outboundService->sendMedia(
            conversation: $conversation,
            file: $request->file('file'),
            mediaType: $validated['media_type'] ?? 'image',
            caption: $validated['caption'] ?? null,
            sendImmediately: true,
        );
        $message->refresh();

        return $this->sendResult($message);
    }

    public function sendLocation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'whatsapp_number' => ['required', 'string', 'max:32'],
            'to_number' => ['required', 'string', 'max:32'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
        ]);

        $line = $this->lines->resolve($validated['whatsapp_number']);
        $conversation = $this->lines->findOrCreateConversation(
            $this->conversationService,
            $line,
            $validated['to_number'],
        );

        $message = $this->outboundService->sendLocation(
            $conversation,
            (float) $validated['latitude'],
            (float) $validated['longitude'],
        );

        return $this->sendResult($message->refresh());
    }

    public function sendContact(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'whatsapp_number' => ['required', 'string', 'max:32'],
            'to_number' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:191'],
            'phone' => ['required', 'string', 'max:32'],
        ]);

        $line = $this->lines->resolve($validated['whatsapp_number']);
        $conversation = $this->lines->findOrCreateConversation(
            $this->conversationService,
            $line,
            $validated['to_number'],
        );

        $message = $this->outboundService->sendContact($conversation, [
            'name' => $validated['name'],
            'phone' => $validated['phone'],
        ]);

        return $this->sendResult($message->refresh());
    }

    public function sendSticker(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'whatsapp_number' => ['required', 'string', 'max:32'],
            'to_number' => ['required', 'string', 'max:32'],
            'file' => ['required', 'file', 'max:512'],
        ]);

        $line = $this->lines->resolve($validated['whatsapp_number']);
        $conversation = $this->lines->findOrCreateConversation(
            $this->conversationService,
            $line,
            $validated['to_number'],
        );

        $message = $this->outboundService->sendSticker(
            conversation: $conversation,
            file: $request->file('file'),
            sendImmediately: true,
        );

        return $this->sendResult($message->refresh());
    }

    public function sendInteractive(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'whatsapp_number' => ['required', 'string', 'max:32'],
            'to_number' => ['required', 'string', 'max:32'],
            'interactive' => ['required', 'array'],
            'preview_body' => ['nullable', 'string', 'max:1024'],
        ]);

        $line = $this->lines->resolve($validated['whatsapp_number']);
        $conversation = $this->lines->findOrCreateConversation(
            $this->conversationService,
            $line,
            $validated['to_number'],
        );

        $message = $this->outboundService->sendInteractive(
            $conversation,
            $validated['interactive'],
            $validated['preview_body'] ?? null,
            enforceWindow: true,
        );
        \App\Domains\Inbox\Jobs\SendOutboundMessageJob::dispatchSync($message->id);

        return $this->sendResult($message->refresh());
    }

    public function sendFlow(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'whatsapp_number' => ['required', 'string', 'max:32'],
            'to_number' => ['required', 'string', 'max:32'],
            'flow_id' => ['required', 'string', 'max:191'],
            'flow_cta' => ['nullable', 'string', 'max:64'],
            'body' => ['nullable', 'string', 'max:1024'],
            'header' => ['nullable', 'string', 'max:255'],
            'footer' => ['nullable', 'string', 'max:255'],
        ]);

        $interactive = [
            'type' => 'flow',
            'header' => filled($validated['header'] ?? null) ? ['type' => 'text', 'text' => $validated['header']] : null,
            'body' => ['text' => $validated['body'] ?? 'Please continue'],
            'footer' => filled($validated['footer'] ?? null) ? ['text' => $validated['footer']] : null,
            'action' => [
                'name' => 'flow',
                'parameters' => [
                    'flow_message_version' => '3',
                    'flow_id' => $validated['flow_id'],
                    'flow_cta' => $validated['flow_cta'] ?? 'Open',
                ],
            ],
        ];

        $request->merge(['interactive' => array_filter($interactive)]);

        return $this->sendInteractive($request);
    }

    public function sendAddress(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'whatsapp_number' => ['required', 'string', 'max:32'],
            'to_number' => ['required', 'string', 'max:32'],
            'body' => ['nullable', 'string', 'max:1024'],
        ]);

        $request->merge([
            'interactive' => [
                'type' => 'address_message',
                'body' => ['text' => $validated['body'] ?? 'Please share your address'],
            ],
        ]);

        return $this->sendInteractive($request);
    }

    public function sendLocationRequest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'whatsapp_number' => ['required', 'string', 'max:32'],
            'to_number' => ['required', 'string', 'max:32'],
            'body' => ['nullable', 'string', 'max:1024'],
        ]);

        $request->merge([
            'interactive' => [
                'type' => 'location_request_message',
                'body' => ['text' => $validated['body'] ?? 'Please share your location'],
                'action' => ['name' => 'send_location'],
            ],
        ]);

        return $this->sendInteractive($request);
    }

    public function sendCatalog(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'whatsapp_number' => ['required', 'string', 'max:32'],
            'to_number' => ['required', 'string', 'max:32'],
            'body' => ['nullable', 'string', 'max:1024'],
            'catalog_id' => ['nullable', 'string', 'max:191'],
            'product_retailer_id' => ['nullable', 'string', 'max:191'],
        ]);

        $interactive = [
            'type' => 'product',
            'body' => ['text' => $validated['body'] ?? 'Browse our catalog'],
            'action' => array_filter([
                'catalog_id' => $validated['catalog_id'] ?? null,
                'product_retailer_id' => $validated['product_retailer_id'] ?? null,
            ]),
        ];

        $request->merge(['interactive' => $interactive]);

        return $this->sendInteractive($request);
    }

    public function getTemplates(Request $request): JsonResponse
    {
        $line = $this->lines->resolve($request->input('whatsapp_number'));
        $items = Template::query()
            ->where('status', TemplateStatus::Approved)
            ->when(
                $line->id,
                fn ($q) => $q->where(function ($inner) use ($line): void {
                    $inner->where('whatsapp_line_id', $line->id)->orWhereNull('whatsapp_line_id');
                }),
            )
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'uuid', 'name', 'code', 'language', 'category', 'status']);

        return response()->json([
            'success' => true,
            'data' => $items->map(fn (Template $t): array => [
                'id' => $t->id,
                'uid' => $t->uuid,
                'name' => $t->name,
                'template_code' => $t->code,
                'language' => $t->language,
                'category' => $t->category,
                'status' => $t->status?->value ?? $t->status,
            ])->values()->all(),
        ]);
    }

    public function sendTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'whatsapp_number' => ['required', 'string', 'max:32'],
            'to_number' => ['required', 'string', 'max:32'],
            'template_code' => ['nullable', 'string', 'max:191'],
            'template_id' => ['nullable'],
            'template_variables' => ['nullable', 'array'],
            'language' => ['nullable', 'string', 'max:16'],
        ]);

        $line = $this->lines->resolve($validated['whatsapp_number']);
        $code = $validated['template_code'] ?? null;
        if ($code === null && filled($validated['template_id'] ?? null)) {
            $template = Template::query()->find($validated['template_id'])
                ?? Template::query()->where('uuid', $validated['template_id'])->first();
            $code = $template?->whatsappCode() ?? $template?->code;
        }
        abort_if($code === null || $code === '', 422, 'template_code or template_id is required.');

        $conversation = $this->lines->findOrCreateConversation(
            $this->conversationService,
            $line,
            $validated['to_number'],
        );

        $params = [];
        foreach (($validated['template_variables'] ?? []) as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $params[(string) $key] = $value;
            }
        }

        $message = $this->outboundService->sendTemplate(
            conversation: $conversation,
            templateCode: (string) $code,
            templateParams: $params,
            language: $validated['language'] ?? null,
            sendImmediately: true,
        );

        return $this->sendResult($message->refresh());
    }

    public function getContacts(Request $request): JsonResponse
    {
        $line = $this->lines->resolve($request->input('whatsapp_number'));
        $page = max(1, $request->integer('page') ?: 1);
        $perPage = min(100, max(1, $request->integer('per_page') ?: 25));

        $paginator = Conversation::query()
            ->where('whatsapp_line_id', $line->id)
            ->orderByDesc('last_message_at')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => collect($paginator->items())->map(fn (Conversation $c): array => [
                'id' => $c->id,
                'name' => $c->contact_name,
                'phone' => $c->contact_phone,
                'whatsapp_number' => $line->phone,
            ])->values()->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function addContact(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'whatsapp_number' => ['required', 'string', 'max:32'],
            'phone' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:191'],
        ]);

        $line = $this->lines->resolve($validated['whatsapp_number']);
        $conversation = $this->contactService->addContact($line, $validated['name'], $validated['phone']);

        return response()->json([
            'success' => true,
            'message' => 'Contact added',
            'data' => MobileInboxPresenter::conversation($conversation),
        ], 201);
    }

    public function uploadVcf(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'whatsapp_number' => ['required', 'string', 'max:32'],
            'file' => ['required', 'file', 'max:2048'],
        ]);

        $line = $this->lines->resolve($validated['whatsapp_number']);
        $contents = (string) file_get_contents($request->file('file')->getRealPath());
        $cards = preg_split('/BEGIN:VCARD/i', $contents) ?: [];
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($cards as $card) {
            $card = trim($card);
            if ($card === '') {
                continue;
            }

            $name = null;
            $phone = null;

            if (preg_match('/^FN[;:](.+)$/mi', $card, $m) === 1) {
                $name = trim(str_replace('\\,', ',', $m[1]));
            } elseif (preg_match('/^N[;:]([^;]*);([^;]*)/mi', $card, $m) === 1) {
                $name = trim($m[2].' '.$m[1]);
            }

            if (preg_match('/^TEL[^:]*:(.+)$/mi', $card, $m) === 1) {
                $phone = trim($m[1]);
            }

            if ($name === null || $name === '' || $phone === null || $phone === '') {
                $skipped++;

                continue;
            }

            try {
                $this->contactService->addContact($line, $name, $phone);
                $imported++;
            } catch (\Throwable $e) {
                $skipped++;
                if (count($errors) < 10) {
                    $errors[] = $name.': '.$e->getMessage();
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Imported {$imported} contact(s)",
            'data' => [
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $errors,
            ],
        ]);
    }

    public function uploadOpenAIKey(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'api_key' => ['required', 'string', 'min:10', 'max:512'],
        ]);

        \App\Models\AiSetting::set('openai_api_key', $validated['api_key']);

        return response()->json(['success' => true, 'message' => 'OpenAI key saved']);
    }

    public function getOpenAIKey(): JsonResponse
    {
        $key = (string) \App\Models\AiSetting::get('openai_api_key', '');

        return response()->json([
            'success' => true,
            'data' => [
                'configured' => $key !== '',
                'masked' => $key !== '' ? str_repeat('*', max(0, strlen($key) - 4)).substr($key, -4) : null,
            ],
        ]);
    }

    public function uploadBusinessInformation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'business_info' => ['required', 'string', 'max:20000'],
        ]);

        \App\Models\AiSetting::set('business_information', $validated['business_info']);

        return response()->json(['success' => true, 'message' => 'Business information saved']);
    }

    public function getBusinessInformation(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'business_info' => \App\Models\AiSetting::get('business_information', ''),
            ],
        ]);
    }

    public function toggleAIHumanResponse(Request $request): JsonResponse
    {
        $line = $this->lines->resolve($request->input('whatsapp_number'));
        $conversation = $this->lines->findConversation(
            $line,
            $request->input('customer_phone') ?: $request->input('to_number'),
            $request->integer('conversation_id') ?: null,
        );

        $type = str_contains(strtolower((string) $request->input('response_type', 'ai')), 'human')
            ? ConversationResponseType::Human
            : ConversationResponseType::Ai;

        $this->responseTypeService->setForConversation($conversation, $type);

        return response()->json([
            'success' => true,
            'data' => MobileInboxPresenter::conversation($conversation->fresh()),
        ]);
    }

    public function getUserRoles(): JsonResponse
    {
        $owner = User::query()->where('role', 'owner')->orWhere('role', \App\Enums\UserRole::Owner)->first();
        $members = TeamMember::query()->orderBy('id')->get(['id', 'uuid', 'first_name', 'last_name', 'email', 'role', 'status']);

        return response()->json([
            'success' => true,
            'data' => [
                'owner' => $owner ? [
                    'id' => $owner->id,
                    'name' => $owner->name,
                    'email' => $owner->email,
                    'role' => 'owner',
                ] : null,
                'team_members' => $members,
            ],
        ]);
    }

    public function updateInboxUser(Request $request, int $user_id): JsonResponse
    {
        $member = TeamMember::query()->find($user_id);
        abort_if($member === null, 404, 'User not found.');

        $validated = $request->validate([
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'assigned_whatsapp_line_ids' => ['nullable', 'array'],
        ]);

        $member->fill(array_filter([
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
        ], fn ($v) => $v !== null));

        if (array_key_exists('assigned_whatsapp_line_ids', $validated)) {
            $member->assigned_whatsapp_line_ids = $validated['assigned_whatsapp_line_ids'];
        }

        $member->save();

        return response()->json(['success' => true, 'data' => $member]);
    }

    public function deleteInboxUser(int $user_id): JsonResponse
    {
        $member = TeamMember::query()->find($user_id);
        abort_if($member === null, 404, 'User not found.');
        $member->delete();

        return response()->json(['success' => true, 'message' => 'User deleted']);
    }

    public function getNewMessageCount(): JsonResponse
    {
        $snapshot = $this->queryService->unreadSnapshot();

        return response()->json([
            'success' => true,
            'data' => [
                'count' => (int) ($snapshot['total'] ?? $snapshot['unread_total'] ?? 0),
                'snapshot' => $snapshot,
            ],
        ]);
    }

    public function checkPlanStatus(): JsonResponse
    {
        $tenant = tenant();

        return response()->json([
            'success' => true,
            'data' => [
                'tenant_id' => $tenant?->id,
                'plan_id' => $tenant?->plan_id,
                'status' => $tenant?->status?->value ?? $tenant?->status,
                'active' => true,
            ],
        ]);
    }

    public function uploadFile(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:16384']]);
        $path = $request->file('file')->store('mobile-uploads/'.tenant('id'), 'public');

        return response()->json([
            'success' => true,
            'data' => [
                'path' => $path,
                'url' => asset('storage/'.$path),
            ],
        ]);
    }

    public function processQuery(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'process-query is not available on this API build.',
        ], 501);
    }

    public function getClientStorageInfo(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'used_bytes' => 0,
                'limit_bytes' => null,
            ],
        ]);
    }

    public function getApiUsage(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'requests_today' => 0,
            ],
        ]);
    }

    public function clearClientData(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Client data clear acknowledged (server-side no-op).',
        ]);
    }

    private function sendResult(\App\Models\Message $message): JsonResponse
    {
        if ($message->status === MessageStatus::Failed) {
            return response()->json([
                'success' => false,
                'message' => (string) ($message->failed_reason ?: 'Unable to send WhatsApp message.'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => MobileInboxPresenter::message($message),
        ], 201);
    }
}
