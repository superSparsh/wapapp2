<?php

declare(strict_types=1);

namespace App\Enums;

enum ConversationStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Closed = 'closed';
    case Archived = 'archived';

    public function isOpen(): bool
    {
        return $this === self::Open;
    }
}
