<?php

declare(strict_types=1);

namespace App\Enums;

enum ChatbotFlowStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Draft = 'draft';

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
