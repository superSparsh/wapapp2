<?php

declare(strict_types=1);

namespace App\Enums;

enum ConversationResponseType: string
{
    case Human = 'human_response';
    case Ai = 'ai_response';

    public function isAi(): bool
    {
        return $this === self::Ai;
    }

    public function isHuman(): bool
    {
        return $this === self::Human;
    }
}
