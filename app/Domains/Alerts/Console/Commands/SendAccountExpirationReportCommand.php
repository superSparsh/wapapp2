<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Console\Commands;

use App\Domains\Alerts\Services\AccountExpirationReportService;
use App\Domains\Alerts\Services\AlertDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SendAccountExpirationReportCommand extends Command
{
    protected $signature = 'alerts:account-expiration-report
                            {--force : Send even if this month\'s report was already sent}
                            {--dry-run : Build report and print counts without emailing}';

    protected $description = 'Email monthly account expiration report from tenants.settings.valid_until (≤30d, 31–90d, expired).';

    public function handle(AccountExpirationReportService $reportService, AlertDispatcher $dispatcher): int
    {
        $report = $reportService->buildReport();
        $meta = $report['meta'] ?? [];
        $expiredCount = count($report['expired'] ?? []);
        $within30 = count($report['expiring_within_30'] ?? []);
        $within90 = count($report['expiring_30_90'] ?? []);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Tenants scanned (active+suspended)', (string) ($meta['tenants_scanned'] ?? 0)],
                ['With settings.valid_until', (string) ($meta['with_validity'] ?? 0)],
                ['Missing valid_until', (string) ($meta['missing_validity'] ?? 0)],
                ['Beyond 90 days (excluded)', (string) ($meta['beyond_90'] ?? 0)],
                ['Expiring within 30 days', (string) $within30],
                ['Expiring in 31–90 days', (string) $within90],
                ['Expired', (string) $expiredCount],
            ]
        );

        if ($this->option('dry-run')) {
            $this->info('Dry-run only - no email sent.');

            return self::SUCCESS;
        }

        $monthKey = 'account_expiration_report_sent:'.now()->format('Y-m');

        if (! $this->option('force') && ! Cache::add($monthKey, 1, now()->endOfMonth())) {
            $this->info('Account expiration report already sent this month - skipped. Use --force to resend.');

            return self::SUCCESS;
        }

        $recipients = $this->resolveRecipientEmails();
        if ($recipients === []) {
            if (! $this->option('force')) {
                Cache::forget($monthKey);
            }
            $this->warn('No recipient emails configured (ACCOUNT_EXPIRATION_REPORT_EMAILS / PLAN_EXPIRY_NOTIFICATION).');

            return self::SUCCESS;
        }

        $dispatcher->accountExpirationReport($report, $recipients);

        $this->info('Account expiration report sent to '.count($recipients).' recipient(s).');

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
