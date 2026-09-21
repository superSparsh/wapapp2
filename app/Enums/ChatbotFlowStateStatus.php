<?php

declare(strict_types=1);

namespace App\Enums;

enum ChatbotFlowStateStatus: string
{
    case Active = 'active';
    case Waiting = 'waiting';
    /** Mid delay / typing — owned by chatbot but not resumable by inbound. */
    case Delayed = 'delayed';
    case Completed = 'completed';
    case Expired = 'expired';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Expired], true);
    }
}
