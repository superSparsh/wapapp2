<?php

declare(strict_types=1);

namespace App\Domains\Api\Http\Controllers\V1;

use App\Domains\Audience\Enums\ContactStatus;
use App\Domains\Audience\Services\MailListService;
use App\Http\Controllers\Controller;
use App\Models\MailList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListApiController extends Controller
{
    public function index(Request $request, MailListService $mailListService): JsonResponse
    {
        $paginator = $mailListService->index(
            search: $request->query('search'),
            perPage: (int) $request->integer('per_page', 25),
        );

        return response()->json([
            'data' => collect($paginator->items())->map(fn (MailList $list): array => $this->serialize($list))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(Request $request, MailListService $mailListService): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
        ]);

        $list = $mailListService->store($validated);

        return response()->json([
            'success' => true,
            'data' => $this->serialize($list),
        ], 201);
    }

    public function show(string $uid): JsonResponse
    {
        $list = $this->findOrFail($uid);

        return response()->json([
            'success' => true,
            'data' => $this->serialize($list->loadCount([
                'contacts',
                'contacts as subscribed_count' => fn ($q) => $q->where('status', ContactStatus::Subscribed),
                'contacts as unsubscribed_count' => fn ($q) => $q->where('status', ContactStatus::Unsubscribed),
                'contacts as blacklisted_count' => fn ($q) => $q->where('status', ContactStatus::Blacklisted),
            ])),
        ]);
    }

    public function destroy(string $uid, MailListService $mailListService): JsonResponse
    {
        $list = $this->findOrFail($uid);
        $mailListService->destroy($list);

        return response()->json([
            'success' => true,
            'message' => 'List deleted successfully.',
        ]);
    }

    private function findOrFail(string $uid): MailList
    {
        $list = MailList::query()->where('uuid', $uid)->first();
        abort_if($list === null, 404, 'List not found.');

        return $list;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(MailList $list): array
    {
        return [
            'uid' => $list->uuid,
            'name' => $list->name,
            'contacts_count' => (int) ($list->contacts_count ?? $list->contacts()->count()),
            'subscribed_count' => (int) ($list->subscribed_count ?? 0),
            'unsubscribed_count' => (int) ($list->unsubscribed_count ?? 0),
            'blacklisted_count' => (int) ($list->blacklisted_count ?? 0),
            'created_at' => optional($list->created_at)?->toIso8601String(),
            'updated_at' => optional($list->updated_at)?->toIso8601String(),
        ];
    }
}
