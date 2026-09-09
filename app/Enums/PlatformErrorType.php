<?php

declare(strict_types=1);

namespace App\Enums;

enum PlatformErrorType: string
{
    case Exception = 'exception';
    case Api = 'api';
    case Job = 'job';
}
