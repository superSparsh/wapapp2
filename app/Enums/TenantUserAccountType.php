<?php

declare(strict_types=1);

namespace App\Enums;

enum TenantUserAccountType: string
{
    case Owner = 'owner';
    case Team = 'team';
}
