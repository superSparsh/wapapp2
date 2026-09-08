<?php

declare(strict_types=1);

namespace App\Enums;

enum InboundWebhookEventType: string
{
    case Message = 'message';
    case Status = 'status';
}
