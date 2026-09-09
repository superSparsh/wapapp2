<?php

declare(strict_types=1);

namespace App\Domains\Audience\Services;

use App\Domains\Audience\Enums\ContactStatus;
use App\Domains\Audience\Models\Blacklist;
use App\Enums\ContactOptInStatus;
use App\Models\Contact;
use Illuminate\Pagination\LengthAwarePaginator;

class BlacklistService
{
    /**
     * Paginated blacklist entries.
     */
    public function index(?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        return Blacklist::query()
            ->when($search, fn ($q) => $q->where(function ($q) use ($search): void {
                $q->where('phone', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('reason', 'LIKE', "%{$search}%");
            }))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Add an entry to the blacklist.
     */
    public function store(array $data): Blacklist
    {
        $entry = Blacklist::query()->create($data);
        $this->syncContacts($data['phone'] ?? null, $data['email'] ?? null);

        return $entry;
    }

    /**
     * Remove an entry from the blacklist.
     */
    public function destroy(Blacklist $blacklist): void
    {
        $blacklist->delete();
    }

    /**
     * Bulk import from CSV rows.
     *
     * @param  list<array{phone?: ?string, email?: ?string, reason?: ?string}>  $rows
     */
    public function importRows(array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            $phone = isset($row['phone']) ? trim((string) $row['phone']) : null;
            $email = isset($row['email']) ? trim((string) $row['email']) : null;
            $reason = isset($row['reason']) ? trim((string) $row['reason']) : null;

            if (($phone === null || $phone === '') && ($email === null || $email === '')) {
                continue;
            }

            $exists = Blacklist::query()
                ->where(function ($q) use ($phone, $email): void {
                    if ($phone) {
                        $q->orWhere('phone', $phone);
                    }
                    if ($email) {
                        $q->orWhere('email', $email);
                    }
                })
                ->exists();

            if ($exists) {
                continue;
            }

            Blacklist::query()->create([
                'phone' => $phone ?: null,
                'email' => $email ?: null,
                'reason' => $reason ?: null,
            ]);
            $this->syncContacts($phone, $email);
            $count++;
        }

        return $count;
    }

    private function syncContacts(?string $phone, ?string $email): void
    {
        if (! $phone && ! $email) {
            return;
        }

        Contact::query()
            ->where(function ($q) use ($phone, $email): void {
                if ($phone) {
                    $q->orWhere('phone', $phone);
                }
                if ($email) {
                    $q->orWhere('email', $email);
                }
            })
            ->update([
                'status' => ContactStatus::Blacklisted,
                'opt_in_status' => ContactOptInStatus::OptedOut,
                'opted_out_at' => now(),
            ]);
    }
}
