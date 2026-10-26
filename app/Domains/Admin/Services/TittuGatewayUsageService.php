<?php

declare(strict_types=1);

namespace App\Domains\Admin\Services;

use App\Enums\AiProvider;
use App\Models\AiBot;
use App\Models\AiProviderKey;
use App\Models\AiTokenUsageLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class TittuGatewayUsageService
{
    public function __construct(
        private readonly CrossTenantScanner $scanner,
    ) {}

    /**
     * @return array{
     *     summary: array{customers: int, active_keys: int, active_bots: int, requests_today: int, requests_30d: int, tokens_30d: int},
     *     rows: list<array<string, mixed>>
     * }
     */
    public function report(): array
    {
        $rows = $this->scanner->map(
            fn (): array => $this->tenantRow(),
            null,
            2000,
        )->sortBy([
            ['requests_30d', 'desc'],
            ['tenant_name', 'asc'],
        ])->values();

        return [
            'summary' => [
                'customers' => $rows->count(),
                'active_keys' => (int) $rows->sum('active_keys'),
                'active_bots' => (int) $rows->sum('active_bots'),
                'requests_today' => (int) $rows->sum('requests_today'),
                'requests_30d' => (int) $rows->sum('requests_30d'),
                'tokens_30d' => (int) $rows->sum('tokens_30d'),
            ],
            'rows' => $rows->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tenantRow(): array
    {
        $keys = AiProviderKey::query()
            ->where('provider', AiProvider::Tittu->value)
            ->get(['is_active', 'is_validated']);

        $bots = AiBot::query()
            ->where('provider', AiProvider::Tittu->value)
            ->get(['id', 'name', 'status']);

        $botIds = $bots->pluck('id');
        $usage = $this->usageQuery($botIds);
        $requestsAll = (clone $usage)->count();

        if ($keys->isEmpty() && $bots->isEmpty() && $requestsAll === 0) {
            return [];
        }

        $today = now()->startOfDay();
        $lastUsed = (clone $usage)->max('created_at');

        return [[
            'active_keys' => $keys->where('is_active', true)->count(),
            'keys_total' => $keys->count(),
            'key_validated' => $keys->contains(fn (AiProviderKey $key): bool => $key->is_active && $key->is_validated),
            'active_bots' => $bots->where('status', 'active')->count(),
            'bots_total' => $bots->count(),
            'bot_names' => $bots->pluck('name')->filter()->take(3)->implode(', '),
            'requests_today' => (clone $usage)->where('created_at', '>=', $today)->count(),
            'requests_7d' => (clone $usage)->where('created_at', '>=', now()->subDays(7))->count(),
            'requests_30d' => (clone $usage)->where('created_at', '>=', now()->subDays(30))->count(),
            'requests_all' => $requestsAll,
            'tokens_30d' => (int) (clone $usage)->where('created_at', '>=', now()->subDays(30))->sum('total_tokens'),
            'last_used_at' => $lastUsed !== null
                ? Carbon::parse($lastUsed)->timezone((string) config('app.timezone'))->format('d M Y, H:i')
                : null,
        ]];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $botIds
     * @return Builder<AiTokenUsageLog>
     */
    private function usageQuery($botIds): Builder
    {
        return AiTokenUsageLog::query()->where(function (Builder $query) use ($botIds): void {
            $query->where('provider', AiProvider::Tittu->value);

            if ($botIds->isNotEmpty()) {
                $query->orWhereIn('ai_bot_id', $botIds->all());
            }
        });
    }
}
