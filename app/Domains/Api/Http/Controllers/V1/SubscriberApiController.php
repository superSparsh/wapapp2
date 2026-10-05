<?php

declare(strict_types=1);

namespace App\Domains\Api\Http\Controllers\V1;

use App\Domains\Audience\Services\ContactService;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\MailList;
use App\Support\PhoneNormalizer;
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
            'data' => collect($paginator->items())->map(fn (Contact $c): array => $this->serialize($c))->values(),
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
            abort_if($mailListId === null, 422, 'list_uid not found.');
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
            'data' => $this->serialize($contact),
        ], 201);
    }

    public function show(string $uid): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->serialize($this->findOrFail($uid)->load('mailList:id,uuid,name')),
        ]);
    }

    public function update(string $uid, Request $request, ContactService $contactService): JsonResponse
    {
        $validated = $request->validate([
            'list_uid' => ['nullable', 'string'],
            'whatsapp_number' => ['nullable', 'string', 'max:32'],
            'country_code' => ['nullable', 'string', 'max:8'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:191'],
        ]);

        $contact = $this->findOrFail($uid);
        $payload = [];

        if (array_key_exists('whatsapp_number', $validated) && filled($validated['whatsapp_number'])) {
            $payload['phone'] = PhoneNormalizer::normalize((string) $validated['whatsapp_number'])
                ?? preg_replace('/\D+/', '', (string) $validated['whatsapp_number']);
        }
        if (array_key_exists('country_code', $validated)) {
            $payload['country_code'] = $validated['country_code'];
        }
        if (array_key_exists('email', $validated)) {
            $payload['email'] = $validated['email'];
        }

        $first = trim((string) ($validated['first_name'] ?? ''));
        $last = trim((string) ($validated['last_name'] ?? ''));
        if ($first !== '' || $last !== '' || array_key_exists('first_name', $validated) || array_key_exists('last_name', $validated)) {
            $name = trim($first.' '.$last);
            $payload['name'] = $name !== '' ? $name : $contact->name;
        }

        if (! empty($validated['list_uid'])) {
            $mailListId = MailList::query()->where('uuid', (string) $validated['list_uid'])->value('id');
            abort_if($mailListId === null, 422, 'list_uid not found.');
            $payload['mail_list_id'] = $mailListId;
        }

        $contact = $contactService->update($contact, $payload);

        return response()->json([
            'success' => true,
            'data' => $this->serialize($contact),
        ]);
    }

    public function destroy(string $uid, ContactService $contactService): JsonResponse
    {
        $contact = $this->findOrFail($uid);
        $contactService->destroy($contact);

        return response()->json([
            'success' => true,
            'message' => 'Subscriber deleted successfully.',
        ]);
    }

    private function findOrFail(string $uid): Contact
    {
        $contact = Contact::query()->where('uuid', $uid)->first();
        abort_if($contact === null, 404, 'Subscriber not found.');

        return $contact;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Contact $contact): array
    {
        $name = trim((string) ($contact->name ?? ''));
        $parts = $name !== '' ? preg_split('/\s+/', $name, 2) : ['', ''];

        return [
            'uid' => $contact->uuid,
            'whatsapp_number' => $contact->phone,
            'first_name' => $parts[0] ?? '',
            'last_name' => $parts[1] ?? '',
            'name' => $contact->name,
            'email' => $contact->email,
            'status' => $contact->status?->value ?? (string) $contact->status,
            'list_uid' => $contact->mailList?->uuid ?? MailList::query()->whereKey($contact->mail_list_id)->value('uuid'),
            'created_at' => optional($contact->created_at)?->toIso8601String(),
            'updated_at' => optional($contact->updated_at)?->toIso8601String(),
        ];
    }
}
