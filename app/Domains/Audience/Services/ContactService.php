<?php

declare(strict_types=1);

namespace App\Domains\Audience\Services;

use App\Domains\Audience\Enums\ContactStatus;
use App\Domains\Drip\Services\DripTriggerDispatcher;
use App\Enums\ContactOptInStatus;
use App\Models\Contact;
use Illuminate\Pagination\LengthAwarePaginator;

class ContactService
{
    public function __construct(
        private readonly DripTriggerDispatcher $dripTriggerDispatcher,
    ) {}

    /**
     * Paginated, searchable, filterable contact listing for a mail list.
     */
    public function index(
        ?int $mailListId = null,
        ?string $search = null,
        ?string $status = null,
        ?string $optIn = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $sortBy = 'created_at',
        string $sortDir = 'desc',
        int $perPage = 25,
    ): LengthAwarePaginator {
        $allowedSorts = ['created_at', 'updated_at', 'name', 'phone', 'email'];
        $sortBy = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'created_at';
        $sortDir = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';

        return Contact::query()
            ->when($mailListId, fn ($q) => $q->where('mail_list_id', $mailListId))
            ->search($search)
            ->filterByStatus($status)
            ->filterByOptIn($optIn)
            ->filterByDateRange($dateFrom, $dateTo)
            ->with('tags')
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage);
    }

    /**
     * Create a new contact.
     */
    public function store(array $data, ?array $tags = null): Contact
    {
        $data['status'] ??= ContactStatus::Subscribed;
        $data['opt_in_status'] ??= ContactOptInStatus::OptedIn;
        $data['opted_in_at'] ??= now();

        $contact = Contact::query()->create($data);

        if ($tags) {
            $contact->syncTags($tags);
        }

        $this->dripTriggerDispatcher->dispatchForContact('welcome-new-subscriber', $contact);

        return $contact->load('tags');
    }

    /**
     * Update a contact.
     */
    public function update(Contact $contact, array $data, ?array $tags = null): Contact
    {
        $contact->update($data);

        if ($tags !== null) {
            $contact->syncTags($tags);
        }

        return $contact->fresh()->load('tags');
    }

    /**
     * Soft-delete a contact.
     */
    public function destroy(Contact $contact): void
    {
        $contact->delete();
    }

    /**
     * Subscribe a contact.
     */
    public function subscribe(Contact $contact): void
    {
        $contact->subscribe();
    }

    /**
     * Unsubscribe a contact.
     */
    public function unsubscribe(Contact $contact): void
    {
        $contact->unsubscribe();
    }

    /**
     * Bulk subscribe contacts by IDs.
     */
    public function bulkSubscribe(array $ids): int
    {
        return Contact::query()
            ->whereIn('id', $ids)
            ->update([
                'status' => ContactStatus::Subscribed,
                'opt_in_status' => ContactOptInStatus::OptedIn,
                'opted_in_at' => now(),
            ]);
    }

    /**
     * Bulk unsubscribe contacts by IDs.
     */
    public function bulkUnsubscribe(array $ids): int
    {
        return Contact::query()
            ->whereIn('id', $ids)
            ->update([
                'status' => ContactStatus::Unsubscribed,
                'opt_in_status' => ContactOptInStatus::OptedOut,
                'opted_out_at' => now(),
            ]);
    }

    /**
     * Bulk delete contacts by IDs.
     */
    public function bulkDelete(array $ids): int
    {
        return Contact::query()
            ->whereIn('id', $ids)
            ->delete();
    }

    /**
     * Export contacts as CSV to a file path. Returns the file path.
     */
    public function exportToCsv(?int $mailListId, string $filePath): int
    {
        $handle = fopen($filePath, 'w');

        // Header row
        fputcsv($handle, ['Phone', 'Name', 'Email', 'Country Code', 'Status', 'Opt-in Status', 'Source', 'Created At']);

        $count = 0;
        Contact::query()
            ->when($mailListId, fn ($q) => $q->where('mail_list_id', $mailListId))
            ->cursor()
            ->each(function (Contact $contact) use ($handle, &$count): void {
                fputcsv($handle, [
                    $contact->phone,
                    $contact->name,
                    $contact->email,
                    $contact->country_code,
                    $contact->status?->value ?? '',
                    $contact->opt_in_status?->value ?? '',
                    $contact->source,
                    $contact->created_at?->toDateTimeString(),
                ]);
                $count++;
            });

        fclose($handle);

        return $count;
    }
}
