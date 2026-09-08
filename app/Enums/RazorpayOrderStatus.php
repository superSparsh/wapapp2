<?php

declare(strict_types=1);

namespace App\Enums;

enum RazorpayOrderStatus: string
{
    case Created = 'created';
    case Paid = 'paid';
    case Failed = 'failed';
}
