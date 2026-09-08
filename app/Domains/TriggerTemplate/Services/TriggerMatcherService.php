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

        $ordered = $triggers
            ->sortByDesc(fn (TriggerVariable $trigger): int => mb_strlen($trigger->variable_name))
            ->values();

        foreach ($ordered as $trigger) {
            if ($trigger->variable_name === $anyMessageTrigger) {
                if ($isFirstMessage) {
                    return $trigger;
                }

                continue;
            }

            $keyword = mb_strtolower($trigger->variable_name);

            if ($keyword !== '' && str_contains($normalizedMessage, $keyword)) {
                return $trigger;
            }
        }

        return null;
    }
}
