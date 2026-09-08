<?php

declare(strict_types=1);

namespace App\Enums;

enum TemplateStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Yet to be submitted',
            self::PendingReview => 'Pending review',
            self::Approved => 'Approved',
            self::Rejected => 'Error',
        };
    }

    public function chipVariant(): string
    {
        return match ($this) {
            self::Draft => 'fd-draft',
            self::PendingReview => 'fd-draft',
            self::Approved => 'fd-approved',
            self::Rejected => 'fd-error',
        };
    }
}
