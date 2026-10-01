<?php

declare(strict_types=1);

namespace App\Domains\MobileApi\Http\Controllers;

use App\Domains\Billing\Services\WalletService;
use App\Http\Controllers\Controller;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Legacy GET /api/v1/wallet-transactions for the mobile app wallet widget.
 */
class WalletController extends Controller
{
    public function __construct(
        private readonly WalletService $wallet,
    ) {}

    public function transactions(Request $request): JsonResponse
    {
        $balance = round($this->wallet->balance(), 2);
        if ($balance <= 0) {
            $balance = round((float) (WalletAccount::query()->value('balance') ?? 0), 2);
        }

        $perPage = min(50, max(1, $request->integer('per_page') ?: 25));
        $paginator = WalletTransaction::query()
            ->orderByDesc('id')
            ->paginate($perPage);

        $rows = collect($paginator->items())->map(function (WalletTransaction $tx): array {
            $meta = is_array($tx->metadata) ? $tx->metadata : [];

            return [
                'amount' => (float) $tx->amount,
                'type' => $tx->type?->value ?? (string) $tx->type,
                'campaign' => (string) ($meta['campaign_name'] ?? $meta['campaign'] ?? ''),
                'conversation_category' => (string) ($meta['category_name'] ?? $meta['category'] ?? ''),
                'description' => (string) ($tx->description ?? ''),
                'created_at' => $tx->created_at?->toIso8601String(),
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'current_wallet_amount' => [
                'wallet_amount' => $balance,
                'wallet_balance' => $balance,
            ],
            // Legacy root key the Flutter wallet screen reads:
            'wallet_amount' => $balance,
            'wallet_transactions' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
