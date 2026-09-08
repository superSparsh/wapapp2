<?php

declare(strict_types=1);

namespace App\Enums;

enum VariableType: string
{
    case Static = 'static';
    case Dynamic = 'dynamic';

    public function label(): string
    {
        return match ($this) {
            self::Static => 'Static',
            self::Dynamic => 'Dynamic',
        };
    }
}
