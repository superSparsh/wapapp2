<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationContactType: string
{
    case Email = 'email';
    case Whatsapp = 'whatsapp';
}
