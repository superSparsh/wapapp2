<?php

declare(strict_types=1);

namespace App\Enums;

enum RazorpayOrderPurpose: string
{
    case Subscription = 'subscription';
    case WalletRecharge = 'wallet_recharge';
}
