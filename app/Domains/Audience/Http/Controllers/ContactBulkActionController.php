<?php

declare(strict_types=1);

namespace App\Domains\Audience\Http\Controllers;

use App\Domains\Audience\Services\ContactBulkActionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactBulkActionController extends Controller
{
    public function __construct(
        private readonly ContactBulkActionService $service,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'operation' => ['required', 'string', 'in:move,copy,bulkAssignTags'],
            'contact_ids' => ['required', 'array', 'min:1'],
            'contact_ids.*' => ['integer'],
            'mail_list_id' => ['nullable', 'integer', 'exists:mail_lists,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
        ]);

        $count = $this->service->apply(
            operation: (string) $validated['operation'],
            contactIds: array_map('intval', $validated['contact_ids']),
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
}
