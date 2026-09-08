<?php

declare(strict_types=1);

namespace App\Enums;

enum ChatbotFlowStatAction: string
{
    case Entered = 'entered';
    case Completed = 'completed';
    case Dropped = 'dropped';
    case Error = 'error';
}
