<?php

declare(strict_types=1);

namespace App\Enums;

enum MessageType: string
{
    case Text = 'text';
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case Document = 'document';
    case Template = 'template';
    case Interactive = 'interactive';
    case Location = 'location';
    case Contact = 'contact';
    case Sticker = 'sticker';
    case Reaction = 'reaction';
    case Order = 'order';
    case System = 'system';
}
