<?php

declare(strict_types=1);

namespace App\Domains\Audience\Http\Controllers;

use App\Domains\Audience\Http\Requests\Contact\BulkContactRequest;
use App\Domains\Audience\Http\Requests\Contact\StoreContactRequest;
use App\Domains\Audience\Http\Requests\Contact\UpdateContactRequest;
use App\Domains\Audience\Services\ContactService;
use App\Models\Contact;
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
        $mailListId = $request->has('list') ? $request->integer('list') : null;

        $contacts = $this->service->index(
            mailListId: $mailListId,
            search: $request->get('search'),
            status: $request->get('status'),
            optIn: $request->get('opt_in'),
            dateFrom: $request->get('date_from'),
            dateTo: $request->get('date_to'),
            sortBy: $request->get('sort_by', 'created_at'),
            sortDir: $request->get('sort_dir', 'desc'),
        );

        $mailList = $mailListId ? \App\Models\MailList::query()->find($mailListId) : null;

        return view('audience.subscribers', [
            'contacts' => $contacts,
            'mailListId' => $mailListId,
            'mailList' => $mailList,
            'mailLists' => \App\Models\MailList::query()->orderBy('name')->get(['id', 'name']),
            'phoneCodes' => config('account.phone_codes', []),
            'countries' => config('account.countries', []),
        ]);
    }

    /**
     * Empty state page.
     */
    public function empty(Request $request): View
    {
        return view('audience.subscribers-empty', [
            'mailListId' => $request->integer('list'),
        ]);
    }

    /**
     * Contact detail page.
     */
    public function detail(Request $request): View
    {
        $contact = Contact::query()
            ->with(['tags', 'mailList'])
            ->findOrFail($request->integer('id'));

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

        $this->service->store($data, $tags);

        return redirect()
            ->route('audience.subscribers', array_filter(['list' => $data['mail_list_id'] ?? null]))
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
        $count = $this->service->bulkSubscribe($request->validated()['ids']);

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
        $count = $this->service->bulkUnsubscribe($request->validated()['ids']);

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
        $count = $this->service->bulkDelete($request->validated()['ids']);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "{$count} contact(s) deleted.",
            ]);
        }

        return redirect()->route('audience.subscribers')
            ->with('status', "{$count} contact(s) deleted.");
    }
}
