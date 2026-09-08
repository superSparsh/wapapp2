<?php

declare(strict_types=1);

namespace App\Domains\Account\Services;

use App\Enums\NotificationContactType;
use App\Models\AccountPreference;
use App\Models\NotificationContact;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NotificationContactService
{
    public function preferences(): AccountPreference
    {
        return AccountPreference::current();
    }

    public function setAlertsEnabled(bool $enabled): void
    {
        AccountPreference::current()->update(['alerts_enabled' => $enabled]);
    }

    /** @return Collection<int, NotificationContact> */
    public function listForUser(User $user): Collection
    {
        return NotificationContact::query()
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->get();
    }

    /** @param  array<int, array<string, mixed>>  $rows */
    public function syncForUser(User $user, array $rows, bool $alertsEnabled): void
    {
        DB::transaction(function () use ($user, $rows, $alertsEnabled): void {
            $this->setAlertsEnabled($alertsEnabled);

            $keepIds = [];

            foreach ($rows as $row) {
                $fullName = trim((string) ($row['full_name'] ?? ''));
                $contactInfo = trim((string) ($row['contact_info'] ?? ''));
                $type = NotificationContactType::tryFrom(strtolower((string) ($row['type'] ?? '')));

                if ($fullName === '' || $contactInfo === '' || $type === null) {
                    continue;
                }

                $id = isset($row['id']) ? (int) $row['id'] : null;

                if ($id) {
                    $contact = NotificationContact::query()
                        ->where('user_id', $user->id)
                        ->whereKey($id)
                        ->first();

                    if ($contact !== null) {
                        $contact->update([
                            'full_name' => $fullName,
                            'type' => $type,
                            'contact_info' => $contactInfo,
                        ]);
                        $keepIds[] = $contact->id;

                        continue;
                    }
                }

                $contact = NotificationContact::query()->create([
                    'user_id' => $user->id,
                    'full_name' => $fullName,
                    'type' => $type,
                    'contact_info' => $contactInfo,
                ]);

                $keepIds[] = $contact->id;
            }

            NotificationContact::query()
                ->where('user_id', $user->id)
                ->when($keepIds !== [], fn ($query) => $query->whereNotIn('id', $keepIds))
                ->delete();
        });
    }

    public function delete(User $user, int $contactId): void
    {
        NotificationContact::query()
            ->where('user_id', $user->id)
            ->whereKey($contactId)
            ->delete();
    }
}
