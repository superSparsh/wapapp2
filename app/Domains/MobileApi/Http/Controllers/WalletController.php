<?php

declare(strict_types=1);

namespace App\Domains\MobileApi\Http\Controllers;

use App\Domains\MobileApi\Support\MobileWallet;
use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Legacy GET /api/v1/wallet-transactions for the mobile app wallet widget.
 */
class WalletController extends Controller
{
    public function transactions(Request $request): JsonResponse
    {
        $balance = MobileWallet::balance();
        $amount = MobileWallet::amountString($balance);

        $perPage = min(50, max(1, $request->integer('per_page') ?: 25));
        $paginator = WalletTransaction::query()
            ->orderByDesc('id')
            ->paginate($perPage);

        $rows = collect($paginator->items())->map(function (WalletTransaction $tx): array {
            $meta = is_array($tx->metadata) ? $tx->metadata : [];

            return [
                'amount' => MobileWallet::amountString((float) $tx->amount),
                'type' => $tx->type?->value ?? (string) $tx->type,
                'campaign' => (string) ($meta['campaign_name'] ?? $meta['campaign'] ?? $meta['legacy_sender_name'] ?? ''),
                'conversation_category' => (string) ($meta['category_name'] ?? $meta['category'] ?? $meta['pricing_category'] ?? ''),
                'description' => (string) ($tx->description ?? ''),
                'created_at' => $tx->created_at?->toIso8601String(),
            ];
        })->values()->all();

        $walletBlock = [
            'wallet_amount' => $amount,
            'wallet_balance' => $amount,
            'amount' => $amount,
            'currency' => 'INR',
        ];

        $payload = [
            // Object — Flutter Map.from(current_wallet_amount)['wallet_amount'].
            'current_wallet_amount' => $walletBlock,
            'current_wallet' => $walletBlock,
            // Scalars — double.tryParse(wallet_amount) / display text.
            'wallet_amount' => $amount,
            'wallet_balance' => $amount,
            'wallet_info' => $walletBlock,
            'wallet_transactions' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];

        return response()->json([
            'success' => true,
            'message' => 'Wallet transactions retrieved successfully.',
            // Nested under data (standard mobile envelope).
            'data' => $payload,
            // Root aliases for older clients that skip data[].
            'current_wallet_amount' => $walletBlock,
            'current_wallet' => $walletBlock,
            'wallet_amount' => $amount,
            'wallet_balance' => $amount,
            'wallet_info' => $walletBlock,
            'wallet_transactions' => $rows,
            'meta' => $payload['meta'],
        ]);
    }
}
