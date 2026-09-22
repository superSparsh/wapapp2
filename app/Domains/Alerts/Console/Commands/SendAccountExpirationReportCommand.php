<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Console\Commands;

use App\Domains\Alerts\Services\AlertDispatcher;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;

class SendAccountExpirationReportCommand extends Command
{
    protected $signature = 'alerts:account-expiration-report {--within-days=45 : Include subscriptions ending within N days}';

    protected $description = 'Email admins a monthly account expiration report.';

    public function handle(AlertDispatcher $dispatcher): int
    {
        $within = max(1, (int) $this->option('within-days'));
        $rows = [];

        foreach (Tenant::query()->cursor() as $tenant) {
            tenancy()->initialize($tenant);

            try {
                $subscriptions = Subscription::query()
                    ->where('status', SubscriptionStatus::Active)
                    ->whereNotNull('ends_at')
                    ->whereBetween('ends_at', [now()->startOfDay(), now()->addDays($within)->endOfDay()])
                    ->get();

                foreach ($subscriptions as $subscription) {
                    $plan = Plan::query()->find($subscription->plan_id);
                    $user = User::query()->orderBy('id')->first();
                    $rows[] = [
                        'tenant' => $tenant->company_name ?: $tenant->name ?: (string) $tenant->id,
                        'email' => $user?->email ?: (string) ($tenant->email ?? ''),
                        'plan' => $plan?->name ?? '—',
                        'ends_at' => $subscription->ends_at?->format('d M Y') ?? '—',
                        'days_left' => (int) now()->startOfDay()->diffInDays($subscription->ends_at->copy()->startOfDay(), false),
                    ];
                }
            } finally {
                tenancy()->end();
            }
        }

        $dispatcher->accountExpirationReport($rows);
        $this->info('Account expiration report sent ('.count($rows).' row(s)).');

        return self::SUCCESS;
    }
}
