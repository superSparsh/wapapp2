<?php

declare(strict_types=1);

namespace App\Domains\Billing\Services;

use App\Models\ServiceMessageFreeUsage;
use App\Models\WhatsappLine;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Meta-style free tier: N free SERVICE messages per calendar month per business phone number (WhatsApp line).
 * Quotas are NOT shared across lines — each WhatsApp number has its own 1000.
 */
class ServiceMessageFreeAllowanceService
{
    public function monthlyLimit(): int
    {
        return max(0, (int) config('billing.service_free_messages_per_month', 1000));
    }

    public function isEnabled(): bool
    {
        return $this->monthlyLimit() > 0;
    }

    /**
     * Try to consume one free service-message slot for this line this month.
     * Returns true when the message should be free (no wallet debit).
     *
     * On system errors (missing table, etc.) returns true (fail open — do not charge)
     * so a missing migration cannot silently bill customers.
     */
    public function tryConsumeFree(?int $whatsappLineId, ?Carbon $asOf = null): bool
    {
        $limit = $this->monthlyLimit();
        if ($limit <= 0) {
            return false;
        }

        $lineId = $whatsappLineId ?? $this->defaultLineId();
        if ($lineId === null || $lineId <= 0) {
            // Free tier is enabled but we cannot attribute a line — do NOT bill.
            Log::warning('Service free allowance: no WhatsApp line id; granting free (fail open)', [
                'whatsapp_line_id' => $whatsappLineId,
            ]);

            return true;
        }

        $yearMonth = ($asOf ?? now())->format('Y-m');
        $connection = (new ServiceMessageFreeUsage)->getConnectionName()
            ?? config('database.default');

        try {
            return (bool) DB::connection($connection)->transaction(function () use ($lineId, $yearMonth, $limit): bool {
                $row = $this->lockUsageRow($lineId, $yearMonth);

                if ((int) $row->used_count >= $limit) {
                    Log::info('Service free allowance exhausted for line this month', [
                        'whatsapp_line_id' => $lineId,
                        'year_month' => $yearMonth,
                        'used_count' => (int) $row->used_count,
                        'limit' => $limit,
                    ]);

                    return false;
                }

                $row->forceFill(['used_count' => (int) $row->used_count + 1])->save();

                return true;
            });
        } catch (Throwable $e) {
            Log::error('Service free allowance check failed — skipping wallet charge (fail open)', [
                'whatsapp_line_id' => $lineId,
                'year_month' => $yearMonth,
                'error' => $e->getMessage(),
            ]);

            // Fail open: do not bill when we cannot track free usage (e.g. migration pending).
            return true;
        }
    }

    /**
     * @return array{
     *     limit: int,
     *     limit_per_line: int,
     *     used: int,
     *     remaining: int,
     *     year_month: string,
     *     lines: int,
     *     per_line: list<array{whatsapp_line_id: int, label: string, used: int, remaining: int, limit: int}>
     * }
     */
    public function summary(?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $limitPerLine = $this->monthlyLimit();
        $yearMonth = $asOf->format('Y-m');
        $lines = WhatsappLine::query()->orderBy('id')->get(['id', 'display_name', 'phone', 'is_default']);
        $lineCount = $lines->count();
        $totalLimit = $limitPerLine * max(1, $lineCount);

        $usageByLine = ServiceMessageFreeUsage::query()
            ->where('year_month', $yearMonth)
            ->get()
            ->keyBy('whatsapp_line_id');

        $perLine = [];
        $used = 0;
        foreach ($lines as $line) {
            $lineUsed = (int) ($usageByLine->get($line->id)?->used_count ?? 0);
            $used += $lineUsed;
            $label = trim((string) ($line->display_name ?: $line->phone ?: 'Line #'.$line->id));
            $perLine[] = [
                'whatsapp_line_id' => (int) $line->id,
                'label' => $label !== '' ? $label : 'Line #'.$line->id,
                'used' => $lineUsed,
                'remaining' => max(0, $limitPerLine - $lineUsed),
                'limit' => $limitPerLine,
            ];
        }

        return [
            'limit' => $totalLimit,
            'limit_per_line' => $limitPerLine,
            'used' => $used,
            'remaining' => max(0, $totalLimit - $used),
            'year_month' => $yearMonth,
            'lines' => $lineCount,
            'per_line' => $perLine,
        ];
    }

    private function lockUsageRow(int $lineId, string $yearMonth): ServiceMessageFreeUsage
    {
        $row = ServiceMessageFreeUsage::query()
            ->where('whatsapp_line_id', $lineId)
            ->where('year_month', $yearMonth)
            ->lockForUpdate()
            ->first();

        if ($row !== null) {
            return $row;
        }

        try {
            $row = ServiceMessageFreeUsage::query()->create([
                'whatsapp_line_id' => $lineId,
                'year_month' => $yearMonth,
                'used_count' => 0,
            ]);
        } catch (QueryException $e) {
            // Concurrent first insert — re-read under lock.
            $row = ServiceMessageFreeUsage::query()
                ->where('whatsapp_line_id', $lineId)
                ->where('year_month', $yearMonth)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                throw $e;
            }

            return $row;
        }

        return ServiceMessageFreeUsage::query()
            ->whereKey($row->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function defaultLineId(): ?int
    {
        $id = WhatsappLine::query()->where('is_default', true)->value('id')
            ?? WhatsappLine::query()->value('id');

        return $id !== null ? (int) $id : null;
    }
}
