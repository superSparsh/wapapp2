<?php

declare(strict_types=1);

namespace App\Domains\Audience\Http\Controllers;

use App\Domains\Audience\Http\Requests\Contact\BulkContactRequest;
use App\Domains\Audience\Http\Requests\Contact\StoreContactRequest;
use App\Domains\Audience\Http\Requests\Contact\UpdateContactRequest;
use App\Domains\Audience\Services\ContactService;
use App\Models\Contact;
use App\Models\MailList;
use App\Support\ListingSort;
use App\Support\PublicId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function __construct(
        private readonly ContactService $service,
    ) {}

    /**
     * Subscribers listing — always scoped to a specific mail list.
     * There is no global "All Subscribers" list view.
     */
    public function index(Request $request): View|RedirectResponse
    {
        if (! $request->filled('list')) {
            return redirect()->route('audience.index');
        }

        $mailList = PublicId::findOrFail(MailList::class, (string) $request->input('list'));

        $parsed = ListingSort::fromRequest(
            $request,
            ['created_at', 'updated_at', 'name', 'phone', 'email'],
            'created_at',
            'desc',
        );

        $dateFrom = $request->get('date_from') ?: $request->get('from_date');
        $dateTo = $request->get('date_to') ?: $request->get('to_date');
        $optIn = $request->get('opt_in') ?: $request->get('opt_in_filter');

        $contacts = $this->service->index(
            mailListId: $mailList->id,
            search: $request->get('search'),
            status: $request->get('status'),
            optIn: is_string($optIn) ? $optIn : null,
            dateFrom: is_string($dateFrom) ? $dateFrom : null,
            dateTo: is_string($dateTo) ? $dateTo : null,
            sortBy: $parsed['sort'],
            sortDir: $parsed['direction'],
        );

        return view('audience.subscribers', [
            'contacts' => $contacts,
            'mailListId' => $mailList->uuid,
            'mailList' => $mailList,
            'mailLists' => MailList::query()->orderBy('name')->get(['id', 'uuid', 'name']),
            'search' => $request->get('search', ''),
            'dateFrom' => is_string($dateFrom) ? $dateFrom : '',
            'dateTo' => is_string($dateTo) ? $dateTo : '',
            'optIn' => is_string($optIn) ? $optIn : '',
            'currentSort' => $parsed['sort'],
            'currentDirection' => $parsed['direction'],
        ]);
    }

    /**
     * Empty state page.
     */
    public function empty(Request $request): View|RedirectResponse
    {
        if (! $request->filled('list')) {
            return redirect()->route('audience.index');
        }

        $mailList = PublicId::findOrFail(MailList::class, (string) $request->input('list'));

        return view('audience.subscribers-empty', [
            'mailListId' => $mailList->uuid,
        ]);
    }

    /**
     * Contact detail page.
     */
    public function detail(Request $request): View
    {
        $contact = PublicId::findOrFail(Contact::class, (string) $request->input('id'));
        $contact->load(['tags', 'mailList']);

        $listUuid = $request->filled('list')
            ? (string) $request->input('list')
            : $contact->mailList?->uuid;

        return view('audience.subscribers-detail', [
            'contact' => $contact,
            'mailListId' => $listUuid,
        ]);
    }

    /**
     * Store a new contact.
     */
    public function store(StoreContactRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $tags = $data['tags'] ?? null;
        unset($data['tags']);

        $mailList = PublicId::find(MailList::class, $data['mail_list_id'] ?? null);
        $data['mail_list_id'] = $mailList?->id;

        $this->service->store($data, $tags);

        return $this->redirectToListSubscribers($mailList?->uuid, 'Contact created successfully.');
    }

    /**
     * Update a contact.
     */
    public function update(UpdateContactRequest $request, Contact $contact): RedirectResponse
    {
        $data = $request->validated();
        $tags = $data['tags'] ?? null;
        unset($data['tags']);

        if (filled($request->input('tags_raw'))) {
            $tags = array_values(array_filter(array_map(
                'trim',
                preg_split('/[,|]+/', (string) $request->input('tags_raw')) ?: []
            )));
        }

        if (array_key_exists('mail_list_id', $data)) {
            $mailList = PublicId::find(MailList::class, $data['mail_list_id'] ?? null);
            $data['mail_list_id'] = $mailList?->id;
        }

        $this->service->update($contact, $data, $tags);

        $listUuid = $request->filled('list')
            ? (string) $request->input('list')
            : (
                $contact->mail_list_id
                    ? MailList::query()->whereKey($contact->mail_list_id)->value('uuid')
                    : null
            );

        return $this->redirectToListSubscribers($listUuid, 'Contact updated successfully.');
    }

    /**
     * Delete a contact.
     */
    public function destroy(Contact $contact): RedirectResponse
    {
        $listUuid = $contact->mail_list_id
            ? MailList::query()->whereKey($contact->mail_list_id)->value('uuid')
            : null;

        $this->service->destroy($contact);

        return $this->redirectToListSubscribers(
            is_string($listUuid) ? $listUuid : null,
            'Contact deleted successfully.',
        );
    }

    /**
     * Subscribe contacts.
     */
    public function subscribe(BulkContactRequest $request): JsonResponse|RedirectResponse
    {
        $ids = $this->resolveContactIds($request->validated()['ids']);
        $count = $this->service->bulkSubscribe($ids);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "{$count} contact(s) subscribed.",
            ]);
        }

        return $this->redirectToListSubscribers(
            $request->filled('list') ? (string) $request->input('list') : null,
            "{$count} contact(s) subscribed.",
            array_filter(['status' => $request->input('status')]),
        );
    }

    /**
     * Unsubscribe contacts.
     */
    public function unsubscribe(BulkContactRequest $request): JsonResponse|RedirectResponse
    {
        $ids = $this->resolveContactIds($request->validated()['ids']);
        $count = $this->service->bulkUnsubscribe($ids);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "{$count} contact(s) unsubscribed.",
            ]);
        }

        return $this->redirectToListSubscribers(
            $request->filled('list') ? (string) $request->input('list') : null,
            "{$count} contact(s) unsubscribed.",
            array_filter(['status' => $request->input('status')]),
        );
    }

    /**
     * Bulk delete contacts.
     */
    public function bulkDelete(BulkContactRequest $request): JsonResponse|RedirectResponse
    {
        $ids = $this->resolveContactIds($request->validated()['ids']);
        $count = $this->service->bulkDelete($ids);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "{$count} contact(s) deleted.",
            ]);
        }

        return $this->redirectToListSubscribers(
            $request->filled('list') ? (string) $request->input('list') : null,
            "{$count} contact(s) deleted.",
            array_filter(['status' => $request->input('status')]),
        );
    }

    /**
     * @param  list<int|string>  $values
     * @return list<int>
     */
    private function resolveContactIds(array $values): array
    {
        $ids = [];
        $uuids = [];

        foreach ($values as $value) {
            if (is_numeric($value)) {
                $ids[] = (int) $value;
            } else {
                $uuids[] = (string) $value;
            }
        }

        return Contact::query()
            ->where(function ($query) use ($ids, $uuids): void {
                if ($ids !== []) {
                    $query->orWhereIn('id', $ids);
                }
                if ($uuids !== []) {
                    $query->orWhereIn('uuid', $uuids);
                }
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function redirectToListSubscribers(?string $listUuid, string $status, array $query = []): RedirectResponse
    {
        if (! filled($listUuid)) {
            return redirect()->route('audience.index')->with('status', $status);
        }

        return redirect()
            ->route('audience.subscribers', array_merge(['list' => $listUuid], $query))
            ->with('status', $status);
    }
}
