<?php

declare(strict_types=1);

namespace App\Domains\TriggerTemplate\Enums;

enum TriggerFireResult: string
{
    case NoMatch = 'no_match';
    case WalletBlocked = 'wallet_blocked';
    case Fired = 'fired';
    case SendFailed = 'send_failed';
}
