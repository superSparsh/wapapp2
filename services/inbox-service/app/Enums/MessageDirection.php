<?php

declare(strict_types=1);

namespace App\Enums;

enum MessageDirection: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';

    public function isInbound(): bool
    {
        return $this === self::Inbound;
    }

    public function isOutbound(): bool
    {
        return $this === self::Outbound;
    }
}
