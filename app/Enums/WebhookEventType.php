<?php

declare(strict_types=1);

namespace App\Enums;

enum WebhookEventType: string
{
    case NewLead = 'new_lead';

    public function label(): string
    {
        return match ($this) {
            self::NewLead => 'New Lead Created',
        };
    }

    /**
     * @return list<self>
     */
    public static function values(): array
    {
        return self::cases();
    }
}
