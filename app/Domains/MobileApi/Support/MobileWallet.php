<?php

declare(strict_types=1);

namespace App\Domains\MobileApi\Support;

use App\Domains\Billing\Services\WalletService;
use App\Models\WalletAccount;
use Throwable;

/**
 * Mobile/Flutter wallet helpers.
 *
 * Legacy Flutter often does double.tryParse(json['wallet_amount']) which fails
 * when the value is a JSON number — return money as "16849.28" strings.
 */
final class MobileWallet
{
    public static function balance(): float
    {
        try {
            $balance = round(app(WalletService::class)->balance(), 2);
            if ($balance > 0) {
                return $balance;
            }
        } catch (Throwable) {
            //
        }

        return round((float) (WalletAccount::query()->value('balance') ?? 0), 2);
    }

    /**
     * String form Flutter can parse; also fine for display.
     */
    public static function amountString(?float $balance = null): string
    {
        return number_format($balance ?? self::balance(), 2, '.', '');
    }

    /**
     * Common wallet keys used across login / dashboard / inbox.
     *
     * @return array{wallet_amount: string, wallet_balance: string, amount: string, currency: string}
     */
    public static function fields(?float $balance = null): array
    {
        $amount = self::amountString($balance);

        return [
            'wallet_amount' => $amount,
            'wallet_balance' => $amount,
            'amount' => $amount,
            'currency' => 'INR',
        ];
    }
}
