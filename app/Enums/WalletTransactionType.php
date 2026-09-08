<?php

declare(strict_types=1);

namespace App\Enums;

enum WalletTransactionType: string
{
    case Credit = 'credit';
    case Debit = 'debit';
    public function label(): string
    {
        return match ($this) {
            self::Credit => 'Credit',
            self::Debit => 'Withdrawal',
        };
    }

    public function signPrefix(): string
    {
        return match ($this) {
            self::Credit => '+',
            self::Debit => '-',
        };
    }
}
