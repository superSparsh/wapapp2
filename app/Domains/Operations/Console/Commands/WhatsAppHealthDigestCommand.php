<?php

declare(strict_types=1);

namespace App\Domains\Operations\Console\Commands;

use App\Domains\Operations\Services\WhatsAppHealthDigestService;
use Illuminate\Console\Command;

class WhatsAppHealthDigestCommand extends Command
{
    protected $signature = 'operations:whatsapp-health-digest {--force : Send even if digest already sent today}';

    protected $description = 'Email WhatsApp Health daily digest to admins (legacy parity).';

    public function handle(WhatsAppHealthDigestService $digest): int
    {
        $sent = $digest->sendDailyDigest((bool) $this->option('force'));

        if ($sent === 0) {
            $this->info('Digest already sent today — skipped.');

            return self::SUCCESS;
        }

        $this->info('WhatsApp Health digest sent.');

        return self::SUCCESS;
    }
}
