<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Enums;

enum IntegrationStatus: string
{
    case Enabled  = 'enabled';
    case Disabled = 'disabled';

    public function isEnabled(): bool
    {
        return $this === self::Enabled;
    }

    public function label(): string
    {
        return match ($this) {
            self::Enabled  => 'Enabled',
            self::Disabled => 'Disabled',
        };
    }

    public function toggle(): self
    {
        return $this === self::Enabled ? self::Disabled : self::Enabled;
    }
}
