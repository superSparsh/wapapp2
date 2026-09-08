<?php

declare(strict_types=1);

namespace App\Domains\Api\Http\Controllers\V1;

use App\Domains\Audience\Services\ContactService;
use App\Http\Controllers\Controller;
use App\Models\MailList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriberApiController extends Controller
{
    public function index(Request $request, ContactService $contactService): JsonResponse
    {
        $mailListId = null;

        if ($request->filled('list_uid')) {
            $mailListId = MailList::query()
                ->where('uuid', (string) $request->query('list_uid'))
                ->value('id');
        }

        $paginator = $contactService->index(
            mailListId: $mailListId ? (int) $mailListId : null,
            search: $request->query('search'),
            perPage: (int) $request->integer('per_page', 25),
        );

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(Request $request, ContactService $contactService): JsonResponse
    {
        $validated = $request->validate([
            'list_uid' => ['nullable', 'string'],
            'whatsapp_number' => ['required', 'string', 'max:32'],
            'country_code' => ['nullable', 'string', 'max:8'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:191'],
        ]);

        $mailListId = null;

        if (! empty($validated['list_uid'])) {
            $mailListId = MailList::query()
                ->where('uuid', (string) $validated['list_uid'])
                ->value('id');
        }

        $name = trim(((string) ($validated['first_name'] ?? '')).' '.((string) ($validated['last_name'] ?? '')));

        $contact = $contactService->store([
            'phone' => (string) $validated['whatsapp_number'],
            'country_code' => $validated['country_code'] ?? null,
            'name' => $name !== '' ? $name : null,
            'email' => $validated['email'] ?? null,
            'mail_list_id' => $mailListId,
            'source' => 'api',
        ]);

        return response()->json([
            'success' => true,
            'data' => $contact,
        ], 201);
    }
}
