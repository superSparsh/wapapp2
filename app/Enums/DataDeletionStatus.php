<?php

declare(strict_types=1);

namespace App\Enums;

enum DataDeletionStatus: string
{
    case Scheduled = 'scheduled';
    case Processing = 'processing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Failed = 'failed';
}
