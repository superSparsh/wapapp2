<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Services;

use App\Enums\BusinessInfoContentType;
use App\Enums\EmbeddingStatus;
use App\Models\AiBot;
use App\Models\AiBusinessInfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AiBusinessInfoService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(AiBot $bot, array $data): AiBusinessInfo
    {
        return DB::transaction(function () use ($bot, $data): AiBusinessInfo {
            return AiBusinessInfo::query()->create([
                'ai_bot_id' => $bot->id,
                'title' => $data['title'],
                'content_type' => $data['content_type'] ?? BusinessInfoContentType::Text->value,
                'content' => $data['content'] ?? '',
                'file_path' => $data['file_path'] ?? null,
                'file_name' => $data['file_name'] ?? null,
                'embedding_status' => EmbeddingStatus::Pending->value,
            ]);
        });
    }

    public function upload(AiBot $bot, UploadedFile $file, string $title): AiBusinessInfo
    {
        $path = $file->store("ai-business-info/{$bot->id}", 'local');

        return $this->create($bot, [
            'title' => $title,
            'content_type' => BusinessInfoContentType::Document->value,
            'content' => '',
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AiBusinessInfo $info, array $data): AiBusinessInfo
    {
        $info->forceFill(array_filter($data, fn ($v) => $v !== null))->save();

        return $info->refresh();
    }

    public function delete(AiBusinessInfo $info): void
    {
        if ($info->file_path) {
            Storage::disk('local')->delete($info->file_path);
        }

        $info->delete();
    }

    /**
     * Get all pending embeddings for a bot.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, AiBusinessInfo>
     */
    public function pendingEmbeddings(int $botId): \Illuminate\Database\Eloquent\Collection
    {
        return AiBusinessInfo::query()
            ->where('ai_bot_id', $botId)
            ->where('embedding_status', EmbeddingStatus::Pending)
            ->get();
    }
}
