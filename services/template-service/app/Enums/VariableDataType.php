<?php

declare(strict_types=1);

namespace App\Enums;

enum VariableDataType: string
{
    case String = 'string';
    case Number = 'number';
    case Url = 'url';
    case Image = 'image';
    case Video = 'video';
    case Pdf = 'pdf';

    public function label(): string
    {
        return match ($this) {
            self::String => 'String',
            self::Number => 'Number',
            self::Url => 'URL',
            self::Image => 'Image',
            self::Video => 'Video',
            self::Pdf => 'PDF',
        };
    }

    public function isMedia(): bool
    {
        return in_array($this, [self::Image, self::Video, self::Pdf], true);
    }

    /** @return list<self> */
    public static function selectable(): array
    {
        return [
            self::String,
            self::Number,
            self::Url,
            self::Image,
            self::Video,
            self::Pdf,
        ];
    }
}
