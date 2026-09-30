<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Support;

use App\Domains\LegacyMigration\DTO\LegacyCustomerSnapshot;
use Illuminate\Support\Carbon;

final class MigrationSinceCounter
{
    public function __construct(
        private readonly LegacyConnection $legacy,
    ) {}

    /**
     * @return array<string, int>
     */
    public function count(LegacyCustomerSnapshot $customer, Carbon $since): array
    {
        $sinceAt = $since->toDateTimeString();
        $out = [];

        if ($this->legacy->tableExists('subscribers') && $this->legacy->tableExists('mail_lists')) {
            $listIds = $this->legacy->db()->table('mail_lists')
                ->where('customer_id', $customer->id)
                ->pluck('id');
            $q = $this->legacy->db()->table('subscribers')->whereIn('mail_list_id', $listIds);
            if ($this->legacy->hasColumn('subscribers', 'created_at')) {
                $q->where('created_at', '>=', $sinceAt);
            }
            $out['subscribers'] = (int) $q->count();
        }

        if ($this->legacy->tableExists('wallet_transactions')) {
            $q = $this->legacy->db()->table('wallet_transactions')->where('customer_id', $customer->id);
            if ($this->legacy->hasColumn('wallet_transactions', 'created_at')) {
                $q->where('created_at', '>=', $sinceAt);
            }
            $out['wallet_tx'] = (int) $q->count();
        }

        if ($this->legacy->tableExists('sub_replies') && $this->legacy->tableExists('new_contacts')) {
            $phones = $this->legacy->db()->table('new_contacts')
                ->where('customer_id', $customer->id)
                ->pluck('phone')
                ->filter()
                ->values()
                ->all();
            if ($phones !== []) {
                $q = $this->legacy->db()->table('sub_replies')->whereIn('msg_to', $phones);
                if ($this->legacy->hasColumn('sub_replies', 'created_at')) {
                    $q->where('created_at', '>=', $sinceAt);
                }
                $out['inbox_threads'] = (int) $q->count();
            } else {
                $out['inbox_threads'] = 0;
            }
        }

        if ($this->legacy->tableExists('new_campaigns')) {
            $q = $this->legacy->db()->table('new_campaigns')->where('customer_id', $customer->id);
            if ($this->legacy->hasColumn('new_campaigns', 'created_at')) {
                $q->where('created_at', '>=', $sinceAt);
            }
            $out['campaigns'] = (int) $q->count();
        }

        if ($this->legacy->tableExists('webhook_logs')) {
            $q = $this->legacy->db()->table('webhook_logs')->where('customer_id', $customer->id);
            if ($this->legacy->hasColumn('webhook_logs', 'created_at')) {
                $q->where('created_at', '>=', $sinceAt);
            } elseif ($this->legacy->hasColumn('webhook_logs', 'sent_at')) {
                $q->where('sent_at', '>=', $sinceAt);
            }
            $out['webhook_logs'] = (int) $q->count();
        }

        return $out;
    }
}
