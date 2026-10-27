<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Console\Commands;

use App\Domains\Inbox\Services\InboxReactionBackfillService;
use App\Enums\MessageDirection;
use App\Enums\MessageType;
use App\Models\Message;
use App\Models\Tenant;
use Illuminate\Console\Command;

class BackfillInboundReactionsCommand extends Command
{
    protected $signature = 'inbox:backfill-inbound-reactions
                            {--tenant= : Tenant id (optional; runs for all tenants when omitted)}
                            {--limit=500 : Max messages to scan per tenant}
                            {--dry-run : Show what would change without writing}';

    protected $description = 'Rewrite legacy inbound reactions from metadata.raw_message into emoji reaction rows';

    public function handle(InboxReactionBackfillService $service): int
    {
        $tenantId = $this->option('tenant');
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $query = Tenant::query()->orderBy('id');
        if (filled($tenantId)) {
            $query->whereKey($tenantId);
        }

        $totalUpdated = 0;
        $totalSkipped = 0;
        $totalUnrecoverable = 0;

        $wasInitialized = tenancy()->initialized;
        $previous = $wasInitialized ? tenant() : null;
        if ($wasInitialized) {
            tenancy()->end();
        }

        foreach ($query->cursor() as $tenant) {
            tenancy()->initialize($tenant);

            try {
                $result = $this->processTenant($service, $limit, $dryRun);
                $totalUpdated += $result['updated'];
                $totalSkipped += $result['skipped'];
                $totalUnrecoverable += $result['unrecoverable'];

                $this->info(sprintf(
                    'Tenant %s - updated: %d, skipped: %d, unrecoverable: %d',
                    $tenant->id,
                    $result['updated'],
                    $result['skipped'],
                    $result['unrecoverable'],
                ));
            } catch (\Throwable $e) {
                report($e);
                $this->error(sprintf('Tenant %s failed: %s', $tenant->id, $e->getMessage()));
            } finally {
                if (tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        }

        if ($previous !== null) {
            tenancy()->initialize($previous);
        }

        $this->info(sprintf(
            'Done%s. updated=%d skipped=%d unrecoverable=%d',
            $dryRun ? ' (dry-run)' : '',
            $totalUpdated,
            $totalSkipped,
            $totalUnrecoverable,
        ));

        if ($totalUnrecoverable > 0) {
            $this->warn('Unrecoverable rows have body "[REACTION message]" (or similar) but no emoji in metadata.raw_message.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array{updated: int, skipped: int, unrecoverable: int}
     */
    private function processTenant(InboxReactionBackfillService $service, int $limit, bool $dryRun): array
    {
        $updated = 0;
        $skipped = 0;
        $unrecoverable = 0;

        $messages = Message::query()
            ->where('direction', MessageDirection::Inbound->value)
            ->where(function ($q): void {
                $q->where('body', '[REACTION message]')
                    ->orWhere('message_type', MessageType::Reaction->value)
                    ->orWhere(function ($q2): void {
                        $q2->where('message_type', MessageType::Text->value)
                            ->whereNotNull('metadata')
                            ->where('metadata', 'like', '%"emoji"%');
                    });
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        foreach ($messages as $message) {
            $metadata = is_array($message->metadata) ? $message->metadata : [];
            if (! $service->isRewriteCandidate($message, $metadata)) {
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $parsed = $service->parseFromStoredMessage($message, $metadata);
                if ($parsed === null) {
                    $unrecoverable++;
                } else {
                    $updated++;
                }

                continue;
            }

            $result = $service->rewriteMessage($message);
            if ($result['updated']) {
                $updated++;
            } elseif ($result['reason'] === 'no_recoverable_payload') {
                $unrecoverable++;
            } else {
                $skipped++;
            }
        }

        return compact('updated', 'skipped', 'unrecoverable');
    }
}
