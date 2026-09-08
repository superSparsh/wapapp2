<?php

declare(strict_types=1);

namespace App\Domains\Audience\Services;

use App\Domains\Audience\Enums\ContactStatus;
use App\Models\Contact;
use App\Models\MailList;
use App\Models\SignupForm;
use Illuminate\Pagination\LengthAwarePaginator;

class MailListService
{
    /**
     * Paginated list of mail lists with contact counts.
     */
    public function index(?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        return MailList::query()
            ->when($search, fn ($q) => $q->where('name', 'LIKE', "%{$search}%"))
            ->withCount([
                'contacts',
                'contacts as subscribed_count' => fn ($q) => $q->where('status', ContactStatus::Subscribed),
                'contacts as unsubscribed_count' => fn ($q) => $q->where('status', ContactStatus::Unsubscribed),
                'contacts as blacklisted_count' => fn ($q) => $q->where('status', ContactStatus::Blacklisted),
            ])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Create a new mail list.
     */
    public function store(array $data): MailList
    {
        return MailList::query()->create($data);
    }

    /**
     * Update a mail list.
     */
    public function update(MailList $mailList, array $data): MailList
    {
        $mailList->update($data);

        return $mailList->fresh();
    }

    /**
     * Soft-delete a mail list.
     */
    public function destroy(MailList $mailList): void
    {
        $mailList->delete();
    }

    /**
     * Overview stats for a single mail list (or global if null).
     *
     * @return array{subscriber_count: int, subscribed_count: int, unsubscribed_count: int, active_percent: float, form_count: int, blacklisted_count: int}
     */
    public function overview(?MailList $mailList = null): array
    {
        $contactsQuery = $mailList
            ? $mailList->contacts()
            : Contact::query();

        $subscriberCount = (clone $contactsQuery)->count();
        $subscribedCount = (clone $contactsQuery)->where('status', ContactStatus::Subscribed)->count();
        $unsubscribedCount = (clone $contactsQuery)->where('status', ContactStatus::Unsubscribed)->count();
        $blacklistedCount = (clone $contactsQuery)->where('status', ContactStatus::Blacklisted)->count();
        $activePercent = $subscriberCount > 0 ? round(($subscribedCount / $subscriberCount) * 100, 2) : 0.0;
        $formCount = SignupForm::query()
            ->when($mailList, fn ($q) => $q->where('list_id', $mailList->id))
            ->count();

        return [
            'subscriber_count' => $subscriberCount,
            'subscribed_count' => $subscribedCount,
            'unsubscribed_count' => $unsubscribedCount,
            'active_percent' => $activePercent,
            'form_count' => $formCount,
            'blacklisted_count' => $blacklistedCount,
        ];
    }

    /**
     * Growth chart data — 16 months of total/unsubscribed counts.
     *
     * @return array{columns: list<string>, total: list<int>, unsubscribed: list<int>}
     */
    public function growthChart(?MailList $mailList = null): array
    {
        $contactsTable = (new Contact())->getTable();
        $baseQuery = Contact::query()
            ->when($mailList, fn ($q) => $q->where('mail_list_id', $mailList->id));

        $columns = [];
        $total = [];
        $unsubscribed = [];

        for ($i = 15; $i >= 0; $i--) {
            $monthEnd = now()->subMonths($i)->endOfMonth();
            $columns[] = $monthEnd->format('y M');

            $total[] = (clone $baseQuery)
                ->where('created_at', '<=', $monthEnd)
                ->count();

            $unsubscribed[] = (clone $baseQuery)
                ->where('status', ContactStatus::Unsubscribed)
                ->where('created_at', '<=', $monthEnd)
                ->count();
        }

        return compact('columns', 'total', 'unsubscribed');
    }

    /**
     * Statistics chart — breakdown by status.
     *
     * @return list<array{value: int, name: string}>
     */
    public function statisticsChart(?MailList $mailList = null): array
    {
        $contactsQuery = $mailList
            ? $mailList->contacts()
            : Contact::query();

        $data = [];

        $subscribed = (clone $contactsQuery)->where('status', ContactStatus::Subscribed)->count();
        if ($subscribed > 0) {
            $data[] = ['value' => $subscribed, 'name' => 'Subscribed'];
        }

        $unsubscribed = (clone $contactsQuery)->where('status', ContactStatus::Unsubscribed)->count();
        if ($unsubscribed > 0) {
            $data[] = ['value' => $unsubscribed, 'name' => 'Unsubscribed'];
        }

        $blacklisted = (clone $contactsQuery)->where('status', ContactStatus::Blacklisted)->count();
        if ($blacklisted > 0) {
            $data[] = ['value' => $blacklisted, 'name' => 'Blacklisted'];
        }

        $spamReported = (clone $contactsQuery)->where('status', ContactStatus::SpamReported)->count();
        if ($spamReported > 0) {
            $data[] = ['value' => $spamReported, 'name' => 'Spam Reported'];
        }

        return $data;
    }
}
