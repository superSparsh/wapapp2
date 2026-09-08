<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Services;

use App\Domains\LegacyMigration\DTO\LegacyCustomerSnapshot;
use App\Domains\LegacyMigration\Support\LegacyConnection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class LegacyCustomerResolver
{
    public function __construct(
        private readonly LegacyConnection $legacy,
    ) {}

    public function resolve(string $identifier): LegacyCustomerSnapshot
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            throw new RuntimeException('Customer identifier is required.');
        }

        $query = $this->legacy->db()->table('customers as c')
            ->leftJoin('users as u', 'u.customer_id', '=', 'c.id')
            ->select([
                'c.id',
                'c.uid',
                'c.wallet_amount',
                'u.email',
                'u.company_name',
                'u.first_name',
                'u.last_name',
                'u.phone',
                'u.password',
            ]);

        if (ctype_digit($identifier)) {
            $query->where('c.id', (int) $identifier);
        } elseif (str_contains($identifier, '@')) {
            $query->whereRaw('LOWER(u.email) = ?', [strtolower($identifier)]);
        } else {
            $query->where('c.uid', $identifier);
        }

        $row = $query->first();

        if ($row === null) {
            throw new RuntimeException("Legacy customer [{$identifier}] was not found.");
        }

        return $this->toSnapshot($row);
    }

    public function resolvePilot(): LegacyCustomerSnapshot
    {
        $maxSubs = (int) config('legacy-migration.pilot.max_subscribers', 5000);

        $candidates = $this->listCandidates(limit: 50);

        $eligible = $candidates->filter(function (array $row) use ($maxSubs) {
            return ($row['lines'] ?? 0) > 0
                && filled($row['email'] ?? null)
                && ($row['subscribers'] ?? 0) <= $maxSubs;
        });

        if ($eligible->isEmpty()) {
            throw new RuntimeException('No suitable pilot customer found (need line + email + subscribers under cap).');
        }

        $best = $eligible->sortByDesc(function (array $row) {
            $features = 0;
            foreach (['templates', 'lists', 'campaigns', 'chatbots', 'drips', 'flows', 'team'] as $key) {
                if (($row[$key] ?? 0) > 0) {
                    $features++;
                }
            }

            return ($features * 100000) + ($row['templates'] ?? 0) + ($row['campaigns'] ?? 0);
        })->first();

        return $this->resolve((string) $best['id']);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listCandidates(int $limit = 25): Collection
    {
        $rows = $this->legacy->db()->table('customers as c')
            ->leftJoin('users as u', 'u.customer_id', '=', 'c.id')
            ->select(['c.id', 'c.uid', 'u.email', 'u.company_name'])
            ->orderByDesc('c.id')
            ->limit(max($limit * 3, 50))
            ->get();

        return $rows->map(function ($row) {
            $counts = $this->countGroups((int) $row->id);

            return [
                'id' => (int) $row->id,
                'uid' => $row->uid,
                'email' => $row->email,
                'company' => $row->company_name,
                ...$counts,
                'total_rows' => array_sum($counts),
            ];
        })
            ->sortByDesc('total_rows')
            ->take($limit)
            ->values();
    }

    /**
     * @return array<string, int>
     */
    public function countGroups(int $customerId): array
    {
        $db = $this->legacy->db();
        $counts = [];

        $simple = [
            'lines' => ['new_contacts', 'customer_id'],
            'templates' => ['new_templates', 'customer_id'],
            'lists' => ['mail_lists', 'customer_id'],
            'campaigns' => ['new_campaigns', 'customer_id'],
            'chatbots' => ['automation_bots', 'customer_id'],
            'drips' => ['automation2s', 'customer_id'],
            'flows' => ['flows', 'customer_id'],
            'team' => ['team_members', 'parent_id'],
            'ai_bots' => ['ai_bots', 'customer_id'],
            'wallet_tx' => ['wallet_transactions', 'customer_id'],
        ];

        foreach ($simple as $key => [$table, $column]) {
            $counts[$key] = $this->safeCount($table, $column, $customerId);
        }

        $counts['subscribers'] = 0;
        if ($this->legacy->tableExists('subscribers') && $this->legacy->tableExists('mail_lists')) {
            $listIds = $db->table('mail_lists')->where('customer_id', $customerId)->pluck('id');
            if ($listIds->isNotEmpty()) {
                $counts['subscribers'] = (int) $db->table('subscribers')->whereIn('mail_list_id', $listIds)->count();
            }
        }

        $counts['inbox_threads'] = $this->countInboxThreads($customerId);

        return $counts;
    }

    private function toSnapshot(object $row): LegacyCustomerSnapshot
    {
        return new LegacyCustomerSnapshot(
            id: (int) $row->id,
            uid: $row->uid !== null ? (string) $row->uid : null,
            email: $row->email !== null ? strtolower((string) $row->email) : null,
            companyName: $row->company_name !== null ? (string) $row->company_name : null,
            firstName: $row->first_name !== null ? (string) $row->first_name : null,
            lastName: $row->last_name !== null ? (string) $row->last_name : null,
            phone: $row->phone !== null ? (string) $row->phone : null,
            passwordHash: $row->password !== null ? (string) $row->password : null,
            walletAmount: isset($row->wallet_amount) ? (float) $row->wallet_amount : null,
            counts: $this->countGroups((int) $row->id),
        );
    }

    private function safeCount(string $table, string $column, int $customerId): int
    {
        if (! $this->legacy->tableExists($table)) {
            return 0;
        }

        if (! Schema::connection($this->legacy->name())->hasColumn($table, $column)) {
            return 0;
        }

        return (int) $this->legacy->db()->table($table)->where($column, $customerId)->count();
    }

    private function countInboxThreads(int $customerId): int
    {
        if (! $this->legacy->tableExists('sub_replies') || ! $this->legacy->tableExists('new_contacts')) {
            return 0;
        }

        $phones = $this->legacy->db()->table('new_contacts')
            ->where('customer_id', $customerId)
            ->pluck('phone')
            ->filter()
            ->values();

        if ($phones->isEmpty()) {
            return 0;
        }

        return (int) $this->legacy->db()->table('sub_replies')
            ->whereIn('msg_to', $phones)
            ->count();
    }
}
