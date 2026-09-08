<?php

declare(strict_types=1);

namespace App\Enums;

enum InboundWebhookStatus: string
{
    case Received = 'received';
    case Processing = 'processing';
    case Processed = 'processed';
    case Failed = 'failed';
    case Duplicate = 'duplicate';
}
