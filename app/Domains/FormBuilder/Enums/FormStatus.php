<?php

declare(strict_types=1);

namespace App\Domains\FormBuilder\Enums;

enum FormStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
        };
    }

    public function chipClass(): string
    {
        return match ($this) {
            self::Active => 'bg-green-50 text-green-500',
            self::Inactive => 'bg-blue-50 text-primary-2',
        };
    }

    public function isEnabled(): bool
    {
        return $this === self::Active;
    }

    public static function fromBoolean(bool $enabled): self
    {
        return $enabled ? self::Active : self::Inactive;
    }
}
