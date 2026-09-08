<?php

declare(strict_types=1);

namespace App\Enums;

enum CampaignStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Sending = 'sending';
    case Completed = 'completed';
    case Paused = 'paused';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::Sending => 'Sending',
            self::Completed => 'Completed',
            self::Paused => 'Paused',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Sending;
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Scheduled], true);
    }

    public function canBeSent(): bool
    {
        return in_array($this, [self::Draft, self::Cancelled], true);
    }
}
