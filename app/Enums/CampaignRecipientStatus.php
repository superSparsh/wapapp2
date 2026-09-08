<?php

declare(strict_types=1);

namespace App\Enums;

enum CampaignRecipientStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Read = 'read';
    case Response = 'response';
    case Unsubscribed = 'unsubscribed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Sent => 'Sent',
            self::Delivered => 'Delivered',
            self::Failed => 'Failed',
            self::Read => 'Read',
            self::Response => 'Response',
            self::Unsubscribed => 'Unsubscribed',
        };
    }
}
