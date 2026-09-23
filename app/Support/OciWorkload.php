<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\InboundWebhookEventType;

/**
 * Resolves which Redis queue a heavy workload should use (OCI vs local).
 */
final class OciWorkload
{
    public static function enabled(): bool
    {
        return (bool) config('oci-workers.enabled', false);
    }

    public static function campaignQueue(): string
    {
        return (string) config('oci-workers.queues.campaign', config('campaigns.queue', 'campaign'));
    }

    public static function statusQueue(): string
    {
        return (string) config('oci-workers.queues.status', 'status');
    }

    public static function importQueue(): string
    {
        return (string) config('oci-workers.queues.import', 'import');
    }

    public static function messagesQueue(): string
    {
        return (string) config('oci-workers.queues.messages', 'messages');
    }

    public static function importRowThreshold(): int
    {
        return max(1, (int) config('oci-workers.import_row_threshold', 30000));
    }

    /**
     * Pick import queue when row count is large (or unknown & file looks huge).
     */
    public static function queueForImport(?int $estimatedRows): string
    {
        $threshold = self::importRowThreshold();

        if ($estimatedRows !== null && $estimatedRows >= $threshold) {
            return self::importQueue();
        }

        return (string) config('queue.connections.redis.queue', 'default');
    }

    /**
     * Queue for inbound Alibaba webhook retry path.
     */
    public static function queueForInboundEvent(InboundWebhookEventType $type): string
    {
        return match ($type) {
            InboundWebhookEventType::Status => self::statusQueue(),
            InboundWebhookEventType::Message => self::messagesQueue(),
            default => (string) config('webhooks.queue', 'default'),
        };
    }

    public static function statusShouldSkipSync(): bool
    {
        return self::enabled() && (bool) config('oci-workers.status_queue_only', true);
    }

    /**
     * Count data rows in a CSV (excludes header). Caps scan for safety.
     */
    public static function estimateCsvRows(string $absolutePath, int $maxScan = 500_000): int
    {
        if ($absolutePath === '' || ! is_readable($absolutePath)) {
            return 0;
        }

        $handle = fopen($absolutePath, 'r');
        if ($handle === false) {
            return 0;
        }

        $rows = 0;
        $line = 0;
        try {
            while (($data = fgetcsv($handle)) !== false) {
                $line++;
                if ($line === 1) {
                    continue; // header
                }
                if ($data === [null] || $data === false) {
                    continue;
                }
                $rows++;
                if ($rows >= $maxScan) {
                    break;
                }
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }
}
