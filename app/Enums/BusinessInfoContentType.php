<?php

declare(strict_types=1);

namespace App\Enums;

enum BusinessInfoContentType: string
{
    case Text = 'text';
    case Document = 'document';
    case Url = 'url';
}
