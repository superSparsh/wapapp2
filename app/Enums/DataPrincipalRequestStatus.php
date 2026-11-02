<?php

declare(strict_types=1);

namespace App\Enums;

enum DataPrincipalRequestStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Rejected = 'rejected';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::InProgress => 'In progress',
            self::Resolved => 'Resolved',
            self::Rejected => 'Rejected',
            self::Closed => 'Closed',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Resolved, self::Rejected, self::Closed => true,
            default => false,
        };
    }
}
