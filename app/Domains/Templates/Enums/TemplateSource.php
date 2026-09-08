<?php

declare(strict_types=1);

namespace App\Domains\Templates\Enums;

enum TemplateSource: string
{
    case Local = 'local';
    case Cams = 'cams';
}
