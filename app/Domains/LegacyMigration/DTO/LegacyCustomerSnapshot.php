<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\DTO;

final class LegacyCustomerSnapshot
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $uid,
        public readonly ?string $email,
        public readonly ?string $companyName,
        public readonly ?string $firstName,
        public readonly ?string $lastName,
        public readonly ?string $phone,
        public readonly ?string $passwordHash,
        public readonly ?float $walletAmount,
        /** @var array<string, int> */
        public readonly array $counts,
    ) {}

    public function displayName(): string
    {
        $company = trim((string) $this->companyName);
        if ($company !== '') {
            return $company;
        }

        $name = trim(($this->firstName ?? '').' '.($this->lastName ?? ''));

        return $name !== '' ? $name : (string) ($this->email ?? 'legacy-'.$this->id);
    }

    public function ownerFullName(): string
    {
        $name = trim(($this->firstName ?? '').' '.($this->lastName ?? ''));

        return $name !== '' ? $name : $this->displayName();
    }
}
