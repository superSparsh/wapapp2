<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Console\Commands;

use App\Domains\Alerts\Services\AccountExpirationReportService;
use App\Domains\Alerts\Services\AlertDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SendAccountExpirationReportCommand extends Command
{
    protected $signature = 'alerts:account-expiration-report {--force : Send even if this month\'s report was already sent}';

    protected $description = 'Email monthly account expiration report (expiring within 30 days, 31–90 days, and expired).';

    public function handle(AccountExpirationReportService $reportService, AlertDispatcher $dispatcher): int
    {
        $monthKey = 'account_expiration_report_sent:'.now()->format('Y-m');

        if (! $this->option('force') && ! Cache::add($monthKey, 1, now()->endOfMonth())) {
            $this->info('Account expiration report already sent this month — skipped.');

            return self::SUCCESS;
        }

        $recipients = $this->resolveRecipientEmails();
        if ($recipients === []) {
            if (! $this->option('force')) {
                Cache::forget($monthKey);
            }
            $this->warn('No recipient emails configured for account expiration report.');

            return self::SUCCESS;
        }

        $report = $reportService->buildReport();
        $dispatcher->accountExpirationReport($report, $recipients);

        $expiredCount = count($report['expired'] ?? []);
        $within30 = count($report['expiring_within_30'] ?? []);
        $within90 = count($report['expiring_30_90'] ?? []);

        $this->info("Account expiration report sent to ".count($recipients)." recipient(s). Within 30d: {$within30}, 31–90d: {$within90}, expired: {$expiredCount}.");

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function resolveRecipientEmails(): array
    {
        $configured = (string) config('services.account_expiration.report_emails', '');
        if ($configured === '') {
            $configured = implode(',', (array) config('operational-alerts.admin_emails', []));
        }

        $unique = [];
        foreach (array_map('trim', explode(',', $configured)) as $email) {
            $key = strtolower($email);
            if ($key === '' || isset($unique[$key])) {
                continue;
            }
            $unique[$key] = $email;
        }

        return array_values($unique);
    }
}
