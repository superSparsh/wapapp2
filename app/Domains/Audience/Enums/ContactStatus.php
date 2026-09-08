<?php

declare(strict_types=1);

namespace App\Domains\Audience\Enums;

enum ContactStatus: string
{
    case Subscribed = 'subscribed';
    case Unsubscribed = 'unsubscribed';
    case Blacklisted = 'blacklisted';
    case SpamReported = 'spam_reported';

    public function label(): string
    {
        return match ($this) {
            self::Subscribed => 'Subscribed',
            self::Unsubscribed => 'Unsubscribed',
            self::Blacklisted => 'Blacklisted',
            self::SpamReported => 'Spam Reported',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Subscribed => 'green',
            self::Unsubscribed => 'gray',
            self::Blacklisted => 'red',
            self::SpamReported => 'orange',
        };
    }
}
