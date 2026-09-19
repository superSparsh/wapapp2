<?php

declare(strict_types=1);

namespace App\Domains\TriggerTemplate\Services;

use App\Models\TriggerVariable;
use Illuminate\Support\Collection;

class TriggerMatcherService
{
    /**
     * @param  Collection<int, TriggerVariable>  $triggers
     */
    public function match(Collection $triggers, string $message, bool $isFirstMessage): ?TriggerVariable
    {
        $normalizedMessage = mb_strtolower(trim($message));

        if ($normalizedMessage === '') {
            return null;
        }

        $anyMessageTrigger = (string) config('trigger-template.any_message_trigger');

        $candidates = $triggers
            ->filter(function (TriggerVariable $trigger) use ($anyMessageTrigger, $isFirstMessage, $normalizedMessage): bool {
                if ($trigger->variable_name === $anyMessageTrigger) {
                    return $isFirstMessage;
                }

                return $this->messageMatchesKeyword($normalizedMessage, mb_strtolower(trim($trigger->variable_name)));
            })
            ->values();

        if ($candidates->isEmpty()) {
            return null;
        }

        // Prefer exact match, then longer keyword (avoids short names like "i" / "a" stealing every reply).
        return $candidates
            ->sort(function (TriggerVariable $a, TriggerVariable $b) use ($normalizedMessage): int {
                $aName = mb_strtolower(trim($a->variable_name));
                $bName = mb_strtolower(trim($b->variable_name));
                $aExact = $normalizedMessage === $aName;
                $bExact = $normalizedMessage === $bName;

                if ($aExact !== $bExact) {
                    return $aExact ? -1 : 1;
                }

                $lenCmp = mb_strlen($bName) <=> mb_strlen($aName);
                if ($lenCmp !== 0) {
                    return $lenCmp;
                }

                return $b->id <=> $a->id;
            })
            ->first();
    }

    private function messageMatchesKeyword(string $messageLower, string $keywordLower): bool
    {
        if ($messageLower === '' || $keywordLower === '') {
            return false;
        }

        if ($messageLower === $keywordLower) {
            return true;
        }

        // Keyword appears as a whole word/token inside the inbound message.
        $pattern = '/(?:^|[^\p{L}\p{N}])'.preg_quote($keywordLower, '/').'(?:[^\p{L}\p{N}]|$)/ui';
        if (preg_match($pattern, $messageLower) === 1) {
            return true;
        }

        // Legacy parity: TriggerVariablesRepository used
        // variable_name LIKE '%'.$message.'%' — short replies match longer trigger names.
        if (mb_strlen($messageLower) >= 2 && str_contains($keywordLower, $messageLower)) {
            return true;
        }

        return false;
    }
}
