<?php

declare(strict_types=1);

namespace App\Domains\MobileApi\Http\Controllers;

use App\Domains\MobileApi\Support\MobileWallet;
use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Legacy GET /api/v1/wallet-transactions
 *
 * Exact response shape from Acelle\Http\Controllers\Api\WalletController@index:
 * {
 *   "current_wallet_amount": { "wallet_amount": "<decimal>" },
 *   "wallet_transactions": [ ... ]
 * }
 */
class WalletController extends Controller
{
    public function transactions(Request $request): JsonResponse
    {
        $balance = MobileWallet::balance();
        // Legacy returns customers.wallet_amount as MySQL decimal string.
        $amount = MobileWallet::amountString($balance);

        $perPage = min(50, max(1, $request->integer('per_page') ?: 25));
        $paginator = WalletTransaction::query()
            ->orderByDesc('id')
            ->paginate($perPage);

        $walletTransactions = [];
        foreach ($paginator->items() as $tx) {
            /** @var WalletTransaction $tx */
            $meta = is_array($tx->metadata) ? $tx->metadata : [];

            $walletTransactions[] = [
                'amount' => MobileWallet::amountString((float) $tx->amount),
                'type' => $tx->type?->value ?? (string) $tx->type,
                'campaign' => (string) ($meta['campaign_name'] ?? $meta['campaign'] ?? $meta['legacy_sender_name'] ?? ''),
                'conversation_category' => (string) ($meta['category_name'] ?? $meta['category'] ?? $meta['pricing_category'] ?? ''),
                'description' => (string) ($tx->description ?? ''),
                'created_at' => $tx->created_at?->toDateTimeString(),
            ];
        }

        // Exact legacy keys — Flutter reads current_wallet_amount['wallet_amount'].
        return response()->json([
            'current_wallet_amount' => [
                'wallet_amount' => $amount,
            ],
            'wallet_transactions' => $walletTransactions,
        ]);
    }
}
