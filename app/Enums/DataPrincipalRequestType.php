<?php

declare(strict_types=1);

namespace App\Enums;

enum DataPrincipalRequestType: string
{
    case Access = 'access';
    case Correction = 'correction';
    case Erasure = 'erasure';
    case WithdrawConsent = 'withdraw_consent';
    case Nomination = 'nomination';
    case Grievance = 'grievance';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Access => 'Access / know what we hold',
            self::Correction => 'Correction of personal data',
            self::Erasure => 'Erasure / deletion',
            self::WithdrawConsent => 'Withdraw consent',
            self::Nomination => 'Nominate / update nominee',
            self::Grievance => 'Grievance / complaint',
            self::Other => 'Other rights request',
        };
    }

    /** @return list<self> */
    public static function selectable(): array
    {
        return self::cases();
    }
}
