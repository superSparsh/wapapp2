<?php

declare(strict_types=1);

namespace App\Enums;

enum WebhookSubscriptionStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
