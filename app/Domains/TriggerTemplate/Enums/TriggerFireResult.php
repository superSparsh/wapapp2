<?php

declare(strict_types=1);

namespace App\Domains\TriggerTemplate\Enums;

enum TriggerFireResult: string
{
    case NoMatch = 'no_match';
    case WalletBlocked = 'wallet_blocked';
    case Fired = 'fired';
    /** Chatbot sent a reply but AI Assistant may also answer this turn (e.g. offline hours). */
    case FiredAllowAi = 'fired_allow_ai';
    case SendFailed = 'send_failed';

    public function consumedTurn(): bool
    {
        return $this === self::Fired;
    }

    public function sentReply(): bool
    {
        return $this === self::Fired || $this === self::FiredAllowAi;
    }
}
