<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Services;

use App\Domains\Integration\Services\PhoneLineService;
use App\Enums\AiProvider;
use App\Models\AiBot;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AiBotService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): AiBot
    {
        return DB::transaction(function () use ($data): AiBot {
            $lineId = $this->resolveCreateLineId($data['whatsapp_line_id'] ?? null);

            if (! empty($data['is_default'])) {
                $this->clearDefaultsForLine($lineId);
            }

            $provider = (string) ($data['provider'] ?? AiProvider::OpenAI->value);
            $isTittu = $provider === AiProvider::Tittu->value;
            $embeddingModel = trim((string) ($data['embedding_model'] ?? ''));

            return AiBot::query()->create([
                'name' => $data['name'],
                'type' => $data['type'] ?? null,
                'system_prompt' => $data['system_prompt'] ?? null,
                'provider' => $provider,
                'chat_model' => $isTittu ? 'auto' : ($data['chat_model'] ?? 'gpt-4o-mini'),
                'embedding_model' => $isTittu
                    ? ($embeddingModel !== '' ? $embeddingModel : 'auto')
                    : ($embeddingModel !== '' ? $embeddingModel : 'text-embedding-3-small'),
                'temperature' => $data['temperature'] ?? 0.3,
                'business_information' => $data['business_information'] ?? null,
                'status' => $data['status'] ?? 'active',
                'is_default' => $data['is_default'] ?? false,
                'whatsapp_line_id' => $lineId,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AiBot $bot, array $data): AiBot
    {
        $this->assertCanManage($bot);

        return DB::transaction(function () use ($bot, $data): AiBot {
            if (array_key_exists('whatsapp_line_id', $data)) {
                $lockedLineId = PhoneLineService::lockedLineId();
                if ($lockedLineId !== null) {
                    $data['whatsapp_line_id'] = $lockedLineId;
                } elseif ($data['whatsapp_line_id'] === null || $data['whatsapp_line_id'] === '') {
                    unset($data['whatsapp_line_id']);
                } else {
                    $data['whatsapp_line_id'] = (int) $data['whatsapp_line_id'];
                }
            }

            $lineId = array_key_exists('whatsapp_line_id', $data)
                ? (int) $data['whatsapp_line_id']
                : $bot->whatsapp_line_id;

            if (isset($data['is_default']) && $data['is_default']) {
                $this->clearDefaultsForLine($lineId, $bot->id);
            }

            $provider = (string) ($data['provider'] ?? $bot->provider?->value ?? $bot->provider);
            if ($provider === AiProvider::Tittu->value) {
                $data['chat_model'] = 'auto';
                $embeddingModel = trim((string) ($data['embedding_model'] ?? $bot->embedding_model ?? ''));
                if ($embeddingModel === '' || str_starts_with(strtolower($embeddingModel), 'text-embedding')) {
                    $embeddingModel = 'auto';
                }
                $data['embedding_model'] = $embeddingModel;
            }

            $clearable = ['system_prompt', 'business_information', 'type'];
            $nullable = [];
            foreach ($clearable as $field) {
                if (array_key_exists($field, $data)) {
                    $nullable[$field] = $data[$field];
                    unset($data[$field]);
                }
            }

            $bot->forceFill(array_merge(
                array_filter($data, fn ($v) => $v !== null),
                $nullable,
            ))->save();

            return $bot->refresh();
        });
    }

    public function delete(AiBot $bot): void
    {
        $this->assertCanManage($bot);

        DB::transaction(function () use ($bot): void {
            $bot->businessInfoEntries()->each(function ($entry) {
                $entry->delete();
            });

            $bot->delete();
        });
    }

    public function toggleDefault(AiBot $bot): AiBot
    {
        $this->assertCanManage($bot);

        return DB::transaction(function () use ($bot): AiBot {
            if ($bot->is_default) {
                $bot->forceFill(['is_default' => false])->save();
            } else {
                $this->clearDefaultsForLine($bot->whatsapp_line_id, $bot->id);
                $bot->forceFill(['is_default' => true])->save();
            }

            return $bot->refresh();
        });
    }

    public function assertCanManage(AiBot $bot): void
    {
        $lockedLineId = PhoneLineService::lockedLineId();
        if ($lockedLineId === null) {
            return;
        }

        // Legacy null-line bots are visible while locked; allow manage so they can be reassigned/deleted.
        if ($bot->whatsapp_line_id === null) {
            return;
        }

        if ((int) $bot->whatsapp_line_id !== $lockedLineId) {
            throw new HttpException(403, 'This AI bot belongs to another WhatsApp number.');
        }
    }

    private function resolveCreateLineId(mixed $lineId): int
    {
        $lockedLineId = PhoneLineService::lockedLineId();
        if ($lockedLineId !== null) {
            return $lockedLineId;
        }

        $resolved = (int) $lineId;
        if ($resolved <= 0) {
            throw new InvalidArgumentException('A WhatsApp number is required for AI bots.');
        }

        return $resolved;
    }

    private function clearDefaultsForLine(?int $lineId, ?int $exceptBotId = null): void
    {
        $query = AiBot::query()->where('is_default', true);

        if ($lineId === null) {
            $query->whereNull('whatsapp_line_id');
        } else {
            $query->where('whatsapp_line_id', $lineId);
        }

        if ($exceptBotId !== null) {
            $query->where('id', '!=', $exceptBotId);
        }

        $query->update(['is_default' => false]);
    }
}
