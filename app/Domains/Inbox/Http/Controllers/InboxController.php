<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Http\Controllers;

use App\Domains\Inbox\Http\Requests\AssignInboxConversationRequest;
use App\Domains\Inbox\Http\Requests\SendInboxLocationRequest;
use App\Domains\Inbox\Http\Requests\SendInboxMediaRequest;
use App\Domains\Inbox\Http\Requests\SendInboxMessageRequest;
use App\Domains\Inbox\Http\Requests\SendInboxStickerRequest;
use App\Domains\Inbox\Http\Requests\SendInboxTemplateRequest;
use App\Domains\Inbox\Http\Requests\StoreInboxContactRequest;
use App\Domains\Inbox\Http\Requests\ToggleInboxResponseTypeRequest;
use App\Domains\Inbox\Services\InboxService;
use App\Domains\Inbox\Services\InboxServiceAdapter;
use App\Domains\Templates\Services\TemplateRegistryService;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function requestPayment(
        Request $request,
        Conversation $conversation,
        InboxService $inboxService,
        \App\Domains\Commerce\Services\CommercePaymentService $paymentService,
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

            \App\Domains\Commerce\Jobs\SendPaymentLinkJob::dispatch($payment, (string) tenant('id'));

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
