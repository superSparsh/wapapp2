<?php

declare(strict_types=1);

namespace App\Enums;

enum TemplateSource: string
{
    case Local = 'local';
    case Cams = 'cams';
}
