<?php

declare(strict_types=1);

namespace App\Domains\Operations\Console\Commands;

use App\Domains\Operations\Services\WhatsAppHealthDigestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class WhatsAppHealthDigestCommand extends Command
{
    protected $signature = 'operations:whatsapp-health-digest
        {--force : Send even if digest already sent today}
        {--dry-run : Build and preview the digest without sending email}
        {--to= : Comma-separated test recipient emails (implies --force)}
        {--output= : Write dry-run HTML to this path (default: storage/app/wa-health-digest-preview.html)}';

    protected $description = 'Email WhatsApp Health daily digest to admins (legacy parity).';

    public function handle(WhatsAppHealthDigestService $digest): int
    {
        if ($this->option('dry-run')) {
            return $this->runDryRun($digest);
        }

        $to = $this->parseEmails((string) $this->option('to'));
        $force = (bool) $this->option('force') || $to !== [];

        $sent = $digest->sendDailyDigest($force, $to !== [] ? $to : null);

        if ($sent === 0) {
            $this->info('Digest already sent today - skipped. Use --force to resend, --to=you@email.com for a test send, or --dry-run to preview.');

            return self::SUCCESS;
        }

        if ($to !== []) {
            $this->info('WhatsApp Health digest test email sent to: '.implode(', ', $to));
        } else {
            $this->info('WhatsApp Health digest sent.');
        }

        return self::SUCCESS;
    }

    private function runDryRun(WhatsAppHealthDigestService $digest): int
    {
        $this->info('Building WhatsApp Health digest (dry-run, no email)...');

        $summary = $digest->buildSummary();
        $html = $digest->renderHtml($summary);

        $output = (string) ($this->option('output') ?: storage_path('app/wa-health-digest-preview.html'));
        $directory = dirname($output);
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }
        File::put($output, $html);

        $email = is_array($summary['email'] ?? null) ? $summary['email'] : [];
        $actionCount = count($email['action_items'] ?? $summary['actionItems'] ?? []);

        $this->table(
            ['Field', 'Value'],
            [
                ['Subject', (string) ($email['subject'] ?? '-')],
                ['Preheader', (string) ($email['preheader'] ?? '-')],
                ['Action items', (string) $actionCount],
                ['Delivery rate', number_format((float) data_get($email, 'scorecard.delivery_rate', 0), 1).'%'],
                ['Connected', (string) data_get($email, 'scorecard.connected', 0).' / '.(string) data_get($email, 'scorecard.lines_total', 0)],
                ['Rejected templates', (string) data_get($email, 'rejected_total', data_get($summary, 'overview.templates.rejected', 0))],
                ['HTML preview', $output],
                ['HTML bytes', (string) strlen($html)],
            ],
        );

        $this->info('Dry-run complete. Open the HTML file in a browser to review the email.');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function parseEmails(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $email): string => strtolower(trim($email)),
            explode(',', $raw),
        ), static fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false));
    }
}
