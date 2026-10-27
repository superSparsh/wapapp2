<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Console\Commands;

use App\Domains\Inbox\Jobs\StoreInboundMediaJob;
use App\Domains\Inbox\Services\InboxMediaService;
use App\Enums\MessageDirection;
use App\Enums\MessageType;
use App\Models\Message;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackfillInboundMediaCommand extends Command
{
    protected $signature = 'inbox:backfill-inbound-media
                            {--tenant= : Tenant id (optional; runs for all tenants when omitted)}
                            {--limit=200 : Max messages to process per tenant}
                            {--sync : Download immediately instead of queueing jobs}';

    protected $description = 'Download existing inbound inbox media to the tenant disk and use local URLs in the UI';

    public function handle(InboxMediaService $mediaService): int
    {
        $tenantId = $this->option('tenant');
        $limit = max(1, (int) $this->option('limit'));
        $sync = (bool) $this->option('sync');

        $query = Tenant::query()->orderBy('id');
        if (filled($tenantId)) {
            $query->whereKey($tenantId);
        }

        $totalQueued = 0;
        $totalStored = 0;
        $totalSkipped = 0;
        $totalFailed = 0;

        $wasInitialized = tenancy()->initialized;
        $previous = $wasInitialized ? tenant() : null;
        if ($wasInitialized) {
            tenancy()->end();
        }

        foreach ($query->cursor() as $tenant) {
            tenancy()->initialize($tenant);

            try {
                $result = $this->processTenant($mediaService, (string) $tenant->id, $limit, $sync);
                $totalQueued += $result['queued'];
                $totalStored += $result['stored'];
                $totalSkipped += $result['skipped'];
                $totalFailed += $result['failed'];

                $this->info(sprintf(
                    'Tenant %s - queued: %d, stored: %d, skipped: %d, failed: %d',
                    $tenant->id,
                    $result['queued'],
                    $result['stored'],
                    $result['skipped'],
                    $result['failed'],
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
            'Done. queued=%d stored=%d skipped=%d failed=%d',
            $totalQueued,
            $totalStored,
            $totalSkipped,
            $totalFailed,
        ));

        return self::SUCCESS;
    }

    /**
     * @return array{queued: int, stored: int, skipped: int, failed: int}
     */
    private function processTenant(
        InboxMediaService $mediaService,
        string $tenantId,
        int $limit,
        bool $sync,
    ): array {
        $queued = 0;
        $stored = 0;
        $skipped = 0;
        $failed = 0;

        $messages = Message::query()
            ->where('direction', MessageDirection::Inbound)
            ->whereIn('message_type', [
                MessageType::Image,
                MessageType::Video,
                MessageType::Audio,
                MessageType::Document,
                MessageType::Sticker,
            ])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        foreach ($messages as $message) {
            $metadata = is_array($message->metadata) ? $message->metadata : [];
            $path = trim((string) ($metadata['media_path'] ?? ''));
            if ($path !== '' && str_starts_with($path, $mediaService->inboundDirectory().'/')) {
                $skipped++;

                continue;
            }

            $remote = trim((string) ($metadata['media_url_remote'] ?? $metadata['media_url'] ?? ''));
            if ($remote === '' || preg_match('#^https?://#i', $remote) !== 1) {
                $skipped++;

                continue;
            }

            if ($sync) {
                try {
                    if ($mediaService->persistInboundMessageMedia($message)) {
                        $stored++;
                    } else {
                        $skipped++;
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    Log::warning('Inbound media backfill failed', [
                        'tenant_id' => $tenantId,
                        'message_id' => $message->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                continue;
            }

            StoreInboundMediaJob::dispatch($tenantId, (int) $message->id);
            $queued++;
        }

        return compact('queued', 'stored', 'skipped', 'failed');
    }
}
