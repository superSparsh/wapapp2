<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Enums;

enum NodeProcessResult: string
{
    case Continue = 'continue';
    case WaitForResponse = 'wait_for_response';
    case Delayed = 'delayed';
    case Completed = 'completed';
    case Error = 'error';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Error], true);
    }

    public function haltsExecution(): bool
    {
        return in_array($this, [self::WaitForResponse, self::Delayed, self::Completed, self::Error], true);
    }
}
