<?php

declare(strict_types=1);

namespace App\Enums;

enum AiProvider: string
{
    case OpenAI = 'openai';
    case Gemini = 'gemini';
    case Azure = 'azure';
    case Tittu = 'tittu';

    public function label(): string
    {
        return match ($this) {
            self::OpenAI => 'OpenAI',
            self::Gemini => 'Google Gemini',
            self::Azure => 'Azure OpenAI',
            self::Tittu => 'Tittu Gateway',
        };
    }

    /**
     * Providers shown in AI Assistant. Azure stays internal until its form is wired.
     *
     * @return list<string>
     */
    public static function selectable(): array
    {
        return [
            self::OpenAI->value,
            self::Gemini->value,
            self::Tittu->value,
        ];
    }
}
