<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Http\Controllers;

use App\Domains\Audience\Services\OptInMessageService;
use App\Domains\Commerce\Jobs\SendPaymentLinkJob;
use App\Domains\Commerce\Services\CommercePaymentService;
use App\Domains\Inbox\Http\Requests\AssignInboxConversationRequest;
use App\Domains\Inbox\Http\Requests\SendInboxContactRequest;
use App\Domains\Inbox\Http\Requests\SendInboxFlowRequest;
use App\Domains\Inbox\Http\Requests\SendInboxInteractiveComposerRequest;
use App\Domains\Inbox\Http\Requests\SendInboxLocationRequest;
use App\Domains\Inbox\Http\Requests\SendInboxMediaRequest;
use App\Domains\Inbox\Http\Requests\SendInboxMessageRequest;
use App\Domains\Inbox\Http\Requests\SendInboxStickerRequest;
use App\Domains\Inbox\Http\Requests\SendInboxTemplateRequest;
use App\Domains\Inbox\Http\Requests\StoreInboxContactRequest;
use App\Domains\Inbox\Http\Requests\ToggleInboxResponseTypeRequest;
use App\Domains\Inbox\Services\InboxService;
use App\Domains\Inbox\Services\InboxServiceAdapter;
use App\Domains\Templates\Services\InteractiveMessageService;
use App\Domains\Templates\Services\TemplateRegistryService;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\InteractiveMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InboxController extends Controller
{
    public function index(Request $request, InboxService $inboxService): View
    {
        return view('inbox.index', array_merge(
            $inboxService->indexPayload($request),
            $this->modalPayload($request),
        ));
    }

    public function show(Request $request, Conversation $conversation, InboxService $inboxService): View
    {
        $inboxService->authorizeConversation($conversation);

        return view('inbox.index', array_merge(
            $inboxService->indexPayload($request, $conversation),
            $this->modalPayload($request),
        ));
    }

    public function threads(Request $request, InboxServiceAdapter $adapter): JsonResponse
    {
        return $adapter->threads($request);
    }

    public function messages(
        Request $request,
        Conversation $conversation,
        InboxServiceAdapter $adapter,
    ): JsonResponse {
        return $adapter->messages($request, $conversation);
    }

    public function sendMessage(
        SendInboxMessageRequest $request,
        Conversation $conversation,
        InboxServiceAdapter $adapter,
    ): JsonResponse {
        return $adapter->sendMessage($request, $conversation);
    }

    public function sendMedia(
        SendInboxMediaRequest $request,
        Conversation $conversation,
        InboxServiceAdapter $adapter,
    ): JsonResponse {
        return $adapter->sendMedia($request, $conversation);
    }

    public function sendTemplate(
        SendInboxTemplateRequest $request,
        Conversation $conversation,
        InboxServiceAdapter $adapter,
    ): JsonResponse {
        return $adapter->sendTemplate($request, $conversation);
    }

    public function sendLocation(
        SendInboxLocationRequest $request,
        Conversation $conversation,
        InboxServiceAdapter $adapter,
    ): JsonResponse {
        return $adapter->sendLocation($request, $conversation);
    }

    public function sendSticker(
        SendInboxStickerRequest $request,
        Conversation $conversation,
        InboxServiceAdapter $adapter,
    ): JsonResponse {
        return $adapter->sendSticker($request, $conversation);
    }

    public function sendContact(
        SendInboxContactRequest $request,
        Conversation $conversation,
        InboxServiceAdapter $adapter,
    ): JsonResponse {
        return $adapter->sendContact($request, $conversation);
    }

    public function sendFlow(
        SendInboxFlowRequest $request,
        Conversation $conversation,
        InboxServiceAdapter $adapter,
    ): JsonResponse {
        return $adapter->sendFlow($request, $conversation);
    }

    public function interactiveMessages(
        InboxService $inboxService,
        InteractiveMessageService $interactiveMessageService,
    ): JsonResponse {
        $inboxService->requireDefaultLine();

        $items = $interactiveMessageService->list()
            ->map(fn (InteractiveMessage $message): array => [
                'id' => $message->id,
                'uuid' => $message->uuid,
                'name' => $message->name,
                'type' => $message->type,
                'preview' => Str::limit(
                    (string) ($message->normalizedContent()['body'] ?? ''),
                    80,
                ),
            ])
            ->values()
            ->all();

        return response()->json(['items' => $items]);
    }

    public function sendInteractive(
        Request $request,
        Conversation $conversation,
        InboxServiceAdapter $adapter,
    ): JsonResponse {
        $validated = $request->validate([
            'interactive_message_id' => ['required', 'string'],
        ]);

        return $adapter->sendInteractiveMessage($conversation, (string) $validated['interactive_message_id']);
    }

    public function sendInteractiveComposer(
        SendInboxInteractiveComposerRequest $request,
        Conversation $conversation,
        InboxServiceAdapter $adapter,
    ): JsonResponse {
        return $adapter->sendInteractiveComposer($request, $conversation);
    }

    public function requestPayment(
        Request $request,
        Conversation $conversation,
        InboxService $inboxService,
        CommercePaymentService $paymentService,
    ): JsonResponse {
        $inboxService->authorizeConversation($conversation);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        try {
            $payment = $paymentService->createPaymentLink([
                'customer_name' => (string) ($conversation->contact_name ?: 'Customer'),
                'customer_phone' => (string) $conversation->contact_phone,
                'amount' => $validated['amount'],
                'currency' => 'INR',
                'description' => $validated['description'],
            ]);

            SendPaymentLinkJob::dispatch($payment, (string) tenant('id'));

            return response()->json([
                'ok' => true,
                'payment_link' => $payment->payment_link,
                'message' => [
                    'uuid' => $payment->uuid,
                    'direction' => 'outbound',
                    'body' => sprintf(
                        'Payment request of ₹%s sent%s.',
                        number_format((float) $validated['amount'], 2),
                        $payment->payment_link ? ' — '.$payment->payment_link : ''
                    ),
                    'message_type' => 'system',
                    'created_at' => now()->toIso8601String(),
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function resendOptIn(
        Conversation $conversation,
        InboxService $inboxService,
        OptInMessageService $optInMessageService,
    ): JsonResponse {
        $inboxService->authorizeConversation($conversation);

        $contact = $conversation->contact;

        if ($contact === null) {
            return response()->json(['message' => 'No contact linked to this conversation.'], 422);
        }

        $sent = $optInMessageService->sendOptInToContact(
            contact: $contact,
            line: $conversation->whatsappLine,
            force: true,
        );

        if (! $sent) {
            return response()->json(['message' => 'Unable to send opt-in template.'], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => [
                'body' => 'Opt-in template sent.',
                'is_outbound' => true,
                'message_type' => 'template',
            ],
        ]);
    }

    public function templates(Request $request, InboxService $inboxService, TemplateRegistryService $registry): JsonResponse
    {
        $inboxService->requireDefaultLine();

        return response()->json([
            'items' => $registry->options(),
        ]);
    }

    public function windowStatus(
        Conversation $conversation,
        InboxServiceAdapter $adapter,
    ): JsonResponse {
        return $adapter->windowStatus($conversation);
    }

    public function toggleResponseType(
        ToggleInboxResponseTypeRequest $request,
        Conversation $conversation,
        InboxServiceAdapter $adapter,
    ): JsonResponse {
        return $adapter->toggleResponseType($request, $conversation);
    }

    public function toggleAllResponseType(
        ToggleInboxResponseTypeRequest $request,
        InboxServiceAdapter $adapter,
    ): JsonResponse {
        return $adapter->toggleAllResponseType($request);
    }

    public function exportConversation(
        Conversation $conversation,
        InboxServiceAdapter $adapter,
    ): StreamedResponse {
        return $adapter->exportConversation($conversation);
    }

    public function exportAll(
        Request $request,
        InboxServiceAdapter $adapter,
    ): StreamedResponse {
        return $adapter->exportAll($request);
    }

    public function markRead(
        Conversation $conversation,
        InboxServiceAdapter $adapter,
    ): JsonResponse {
        return $adapter->markRead($conversation);
    }

    public function markAllRead(
        Request $request,
        InboxServiceAdapter $adapter,
    ): JsonResponse {
        return $adapter->markAllRead($request);
    }

    public function storeContact(
        StoreInboxContactRequest $request,
        InboxServiceAdapter $adapter,
    ): JsonResponse {
        return $adapter->storeContact($request);
    }

    public function assign(
        AssignInboxConversationRequest $request,
        Conversation $conversation,
        InboxServiceAdapter $adapter,
    ): JsonResponse {
        return $adapter->assign($request, $conversation);
    }

    /**
     * @return array{activeModal: ?string, variant: string}
     */
    private function modalPayload(Request $request): array
    {
        $modalKey = $request->string('modal')->toString() ?: null;
        $map = config('inbox-modals.query_map', []);

        if ($modalKey !== null && ! array_key_exists($modalKey, $map)) {
            abort(404);
        }

        return [
            'activeModal' => $modalKey ? ($map[$modalKey] ?? null) : null,
            'variant' => $request->string('variant')->toString() === 'alt' ? 'alt' : 'default',
        ];
    }
}
