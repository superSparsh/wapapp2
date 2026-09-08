<?php

declare(strict_types=1);

namespace App\Enums;

enum AiProvider: string
{
    case OpenAI = 'openai';
    case Gemini = 'gemini';
    case Azure = 'azure';

    public function label(): string
    {
        return match ($this) {
            self::OpenAI => 'OpenAI',
            self::Gemini => 'Google Gemini',
            self::Azure => 'Azure OpenAI',
        };
    }
}
