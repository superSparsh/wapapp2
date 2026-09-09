<?php

declare(strict_types=1);

namespace App\Domains\Audience\Http\Controllers;

use App\Domains\Audience\Services\ContactBulkActionService;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\MailList;
use App\Support\PublicId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContactBulkActionController extends Controller
{
    public function __construct(
        private readonly ContactBulkActionService $service,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $count = $this->service->apply(
            operation: (string) $validated['operation'],
            contactIds: $validated['contact_ids'],
            options: [
                'mail_list_id' => $validated['mail_list_id'] ?? null,
                'tags' => $validated['tags'] ?? [],
            ],
        );

        return response()->json([
            'success' => true,
            'affected' => $count,
        ]);
    }

    public function move(Request $request): JsonResponse|RedirectResponse
    {
        return $this->runNamed($request, 'move');
    }

    public function copy(Request $request): JsonResponse|RedirectResponse
    {
        return $this->runNamed($request, 'copy');
    }

    public function bulkTags(Request $request): JsonResponse|RedirectResponse
    {
        return $this->runNamed($request, 'bulkAssignTags');
    }

    private function runNamed(Request $request, string $operation): JsonResponse|RedirectResponse
    {
        $validated = $this->validatePayload($request, $operation);

        $count = $this->service->apply(
            operation: $operation,
            contactIds: $validated['contact_ids'],
            options: [
                'mail_list_id' => $validated['mail_list_id'] ?? null,
                'tags' => $validated['tags'] ?? [],
            ],
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'affected' => $count,
            ]);
        }

        $listUuid = null;
        if (! empty($validated['mail_list_id'])) {
            $listUuid = MailList::query()->whereKey($validated['mail_list_id'])->value('uuid');
        }

        return redirect()
            ->route('audience.subscribers', array_filter(['list' => $listUuid]))
            ->with('status', "{$count} contact(s) updated.");
    }

    /**
     * @return array{operation?: string, contact_ids: list<int>, mail_list_id: ?int, tags: list<string>}
     */
    private function validatePayload(Request $request, ?string $forcedOperation = null): array
    {
        $rules = [
            'ids' => ['nullable', 'array', 'min:1'],
            'ids.*' => ['string'],
            'contact_ids' => ['nullable', 'array', 'min:1'],
            'contact_ids.*' => ['integer'],
            'mail_list_id' => ['nullable'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'tags_raw' => ['nullable', 'string', 'max:1000'],
        ];

        if ($forcedOperation === null) {
            $rules['operation'] = ['required', 'string', 'in:move,copy,bulkAssignTags'];
        }

        $validated = $request->validate($rules);

        $contactIds = array_map('intval', $validated['contact_ids'] ?? []);
        if ($contactIds === [] && ! empty($validated['ids'])) {
            $contactIds = Contact::query()
                ->whereIn('uuid', $validated['ids'])
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        abort_if($contactIds === [], 422, 'Select at least one contact.');

        $mailListId = null;
        if (! empty($validated['mail_list_id'])) {
            $mailList = PublicId::find(MailList::class, (string) $validated['mail_list_id'])
                ?? MailList::query()->find($validated['mail_list_id']);
            $mailListId = $mailList?->id;
        }

        $tags = $validated['tags'] ?? [];
        if ($tags === [] && filled($validated['tags_raw'] ?? null)) {
            $tags = array_values(array_filter(array_map(
                'trim',
                preg_split('/[,|]+/', (string) $validated['tags_raw']) ?: []
            )));
        }

        $payload = [
            'contact_ids' => $contactIds,
            'mail_list_id' => $mailListId,
            'tags' => $tags,
        ];

        if ($forcedOperation === null) {
            $payload['operation'] = $validated['operation'];
        }

        return $payload;
    }
}
