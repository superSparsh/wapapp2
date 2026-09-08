<?php

declare(strict_types=1);

namespace App\Domains\Audience\Services;

use App\Domains\Audience\Models\Blacklist;
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
                    ->orWhere('email', 'LIKE', "%{$search}%");
            }))
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Add an entry to the blacklist.
     */
    public function store(array $data): Blacklist
    {
        return Blacklist::query()->create($data);
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
     * @param list<array{phone?: ?string, email?: ?string, reason?: ?string}> $rows
     */
    public function importRows(array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            $phone = $row['phone'] ?? null;
            $email = $row['email'] ?? null;

            if (! $phone && ! $email) {
                continue;
            }

            // Skip duplicates
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
                'phone' => $phone,
                'email' => $email,
                'reason' => $row['reason'] ?? null,
            ]);
            $count++;
        }

        return $count;
    }
}
