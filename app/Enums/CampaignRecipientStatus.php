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

    /**
     * View Details lists only the clicked status. Read does not also appear under Delivered.
     *
     * @return list<self>|null  null = no filter
     */
    public static function statusesForFilter(?string $status): ?array
    {
        $status = trim((string) ($status ?? ''));
        if ($status === '') {
            return null;
        }

        $enum = self::tryFrom($status);
        if ($enum === null) {
            return null;
        }

        return [$enum];
    }

    public function progressionRank(): int
    {
        return match ($this) {
            self::Pending => 0,
            self::Sent => 1,
            self::Delivered => 2,
            self::Read => 3,
            self::Response => 4,
            self::Failed, self::Unsubscribed => 100,
        };
    }

    public function canTransitionTo(self $incoming): bool
    {
        if ($this === self::Failed || $this === self::Unsubscribed || $this === self::Response) {
            return false;
        }

        if ($incoming === self::Failed) {
            return $this->progressionRank() < self::Delivered->progressionRank();
        }

        return $incoming->progressionRank() >= $this->progressionRank();
    }
}
