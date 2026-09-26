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
     * View-details / export filters keep higher funnel stages in earlier buckets
     * (e.g. Read still appears under Delivered; Response still under Read).
     *
     * @return list<self>|null  null = no filter
     */
    public static function statusesForFilter(?string $status): ?array
    {
        if ($status === null || $status === '') {
            return null;
        }

        $enum = self::tryFrom($status);
        if ($enum === null) {
            return null;
        }

        return match ($enum) {
            self::Delivered => [self::Delivered, self::Read, self::Response],
            self::Read => [self::Read, self::Response],
            default => [$enum],
        };
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
