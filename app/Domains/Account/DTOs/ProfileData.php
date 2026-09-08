<?php

declare(strict_types=1);

namespace App\Domains\Account\DTOs;

final readonly class ProfileData
{
    public function __construct(
        public int $id,
        public string $firstName,
        public string $lastName,
        public string $email,
        public ?string $phone,
        public ?string $avatarUrl,
        public string $timezone,
        public string $countryCode,
        public string $locale,
        public bool $canEditTenantPreferences,
    ) {}

    public function fullName(): string
    {
        return trim($this->firstName.' '.$this->lastName);
    }
}
