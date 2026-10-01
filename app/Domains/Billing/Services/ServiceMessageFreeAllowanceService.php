<?php

declare(strict_types=1);

namespace App\Domains\Billing\Services;

use App\Models\ServiceMessageFreeUsage;
use App\Models\WhatsappLine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Meta-style free tier: N free SERVICE messages per calendar month per business phone number (WhatsApp line).
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
     */
    public function tryConsumeFree(?int $whatsappLineId, ?Carbon $asOf = null): bool
    {
        $limit = $this->monthlyLimit();
        if ($limit <= 0) {
            return false;
        }

        $lineId = $whatsappLineId ?? $this->defaultLineId();
        if ($lineId === null || $lineId <= 0) {
            return false;
        }

        $yearMonth = ($asOf ?? now())->format('Y-m');

        return (bool) DB::transaction(function () use ($lineId, $yearMonth, $limit): bool {
            $row = ServiceMessageFreeUsage::query()
                ->where('whatsapp_line_id', $lineId)
                ->where('year_month', $yearMonth)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                $row = ServiceMessageFreeUsage::query()->create([
                    'whatsapp_line_id' => $lineId,
                    'year_month' => $yearMonth,
                    'used_count' => 0,
                ]);
                $row = ServiceMessageFreeUsage::query()
                    ->whereKey($row->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            if ((int) $row->used_count >= $limit) {
                return false;
            }

            $row->forceFill(['used_count' => (int) $row->used_count + 1])->save();

            return true;
        });
    }

    /**
     * @return array{limit: int, used: int, remaining: int, year_month: string, lines: int}
     */
    public function summary(?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $limitPerLine = $this->monthlyLimit();
        $yearMonth = $asOf->format('Y-m');
        $lines = (int) WhatsappLine::query()->count();
        $totalLimit = $limitPerLine * max(1, $lines);

        $used = (int) ServiceMessageFreeUsage::query()
            ->where('year_month', $yearMonth)
            ->sum('used_count');

        return [
            'limit' => $totalLimit,
            'limit_per_line' => $limitPerLine,
            'used' => $used,
            'remaining' => max(0, $totalLimit - $used),
            'year_month' => $yearMonth,
            'lines' => $lines,
        ];
    }

    private function defaultLineId(): ?int
    {
        $id = WhatsappLine::query()->where('is_default', true)->value('id')
            ?? WhatsappLine::query()->value('id');

        return $id !== null ? (int) $id : null;
    }
}
