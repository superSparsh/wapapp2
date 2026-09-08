<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Services;

use App\Models\AiBot;
use Illuminate\Support\Facades\DB;

class AiBotService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): AiBot
    {
        return DB::transaction(function () use ($data): AiBot {
            // If this bot is set as default, unset other defaults
            if (! empty($data['is_default'])) {
                AiBot::query()->where('is_default', true)->update(['is_default' => false]);
            }

            return AiBot::query()->create([
                'name' => $data['name'],
                'type' => $data['type'] ?? null,
                'system_prompt' => $data['system_prompt'] ?? null,
                'provider' => $data['provider'] ?? 'openai',
                'chat_model' => $data['chat_model'] ?? 'gpt-4o-mini',
                'embedding_model' => $data['embedding_model'] ?? 'text-embedding-3-small',
                'temperature' => $data['temperature'] ?? 0.3,
                'business_information' => $data['business_information'] ?? null,
                'status' => $data['status'] ?? 'active',
                'is_default' => $data['is_default'] ?? false,
                'whatsapp_line_id' => $data['whatsapp_line_id'] ?? null,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AiBot $bot, array $data): AiBot
    {
        return DB::transaction(function () use ($bot, $data): AiBot {
            // If setting as default, unset others
            if (isset($data['is_default']) && $data['is_default']) {
                AiBot::query()
                    ->where('is_default', true)
                    ->where('id', '!=', $bot->id)
                    ->update(['is_default' => false]);
            }

            $bot->forceFill(array_filter($data, fn ($v) => $v !== null))->save();

            return $bot->refresh();
        });
    }

    public function delete(AiBot $bot): void
    {
        DB::transaction(function () use ($bot): void {
            $bot->businessInfoEntries()->each(function ($entry) {
                $entry->delete();
            });

            $bot->delete();
        });
    }

    public function toggleDefault(AiBot $bot): AiBot
    {
        return DB::transaction(function () use ($bot): AiBot {
            if ($bot->is_default) {
                $bot->forceFill(['is_default' => false])->save();
            } else {
                AiBot::query()
                    ->where('is_default', true)
                    ->where('id', '!=', $bot->id)
                    ->update(['is_default' => false]);

                $bot->forceFill(['is_default' => true])->save();
            }

            return $bot->refresh();
        });
    }
}
