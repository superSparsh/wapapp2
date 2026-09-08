<?php

declare(strict_types=1);

namespace App\Domains\Audience\Http\Controllers;

use App\Domains\Audience\Http\Requests\Contact\BulkContactRequest;
use App\Domains\Audience\Http\Requests\Contact\StoreContactRequest;
use App\Domains\Audience\Http\Requests\Contact\UpdateContactRequest;
use App\Domains\Audience\Services\ContactService;
use App\Models\Contact;
use App\Models\MailList;
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
     * Subscribers listing.
     */
    public function index(Request $request): View
    {
        $mailList = $request->filled('list')
            ? PublicId::findOrFail(MailList::class, (string) $request->input('list'))
            : null;

        $contacts = $this->service->index(
            mailListId: $mailList?->id,
            search: $request->get('search'),
            status: $request->get('status'),
            optIn: $request->get('opt_in'),
            dateFrom: $request->get('date_from'),
            dateTo: $request->get('date_to'),
            sortBy: $request->get('sort_by', 'created_at'),
            sortDir: $request->get('sort_dir', 'desc'),
        );

        return view('audience.subscribers', [
            'contacts' => $contacts,
            'mailListId' => $mailList?->uuid,
            'mailList' => $mailList,
            'mailLists' => MailList::query()->orderBy('name')->get(['id', 'uuid', 'name']),
            'phoneCodes' => config('account.phone_codes', []),
            'countries' => config('account.countries', []),
        ]);
    }

    /**
     * Empty state page.
     */
    public function empty(Request $request): View
    {
        $mailList = $request->filled('list')
            ? PublicId::findOrFail(MailList::class, (string) $request->input('list'))
            : null;

        return view('audience.subscribers-empty', [
            'mailListId' => $mailList?->uuid,
        ]);
    }

    /**
     * Contact detail page.
     */
    public function detail(Request $request): View
    {
        $contact = PublicId::findOrFail(Contact::class, (string) $request->input('id'));
        $contact->load(['tags', 'mailList']);

        return view('audience.subscribers-detail', ['contact' => $contact]);
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

        return redirect()
            ->route('audience.subscribers', array_filter(['list' => $mailList?->uuid]))
            ->with('status', 'Contact created successfully.');
    }

    /**
     * Update a contact.
     */
    public function update(UpdateContactRequest $request, Contact $contact): RedirectResponse
    {
        $data = $request->validated();
        $tags = $data['tags'] ?? null;
        unset($data['tags']);

        if (array_key_exists('mail_list_id', $data)) {
            $mailList = PublicId::find(MailList::class, $data['mail_list_id'] ?? null);
            $data['mail_list_id'] = $mailList?->id;
        }

        $this->service->update($contact, $data, $tags);

        return redirect()->route('audience.subscribers')
            ->with('status', 'Contact updated successfully.');
    }

    /**
     * Delete a contact.
     */
    public function destroy(Contact $contact): RedirectResponse
    {
        $this->service->destroy($contact);

        return redirect()->route('audience.subscribers')
            ->with('status', 'Contact deleted successfully.');
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

        return redirect()->route('audience.subscribers')
            ->with('status', "{$count} contact(s) subscribed.");
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

        return redirect()->route('audience.subscribers')
            ->with('status', "{$count} contact(s) unsubscribed.");
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

        return redirect()->route('audience.subscribers')
            ->with('status', "{$count} contact(s) deleted.");
    }

    /**
     * @param  list<string>  $uuids
     * @return list<int>
     */
    private function resolveContactIds(array $uuids): array
    {
        return Contact::query()
            ->whereIn('uuid', $uuids)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
