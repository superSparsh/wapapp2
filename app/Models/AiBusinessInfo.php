<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BusinessInfoContentType;
use App\Enums\EmbeddingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiBusinessInfo extends TenantModel
{
    use HasFactory;

    protected $table = 'ai_business_info';

    protected $fillable = [
        'ai_bot_id',
        'title',
        'content_type',
        'content',
        'file_path',
        'file_name',
        'embedding_status',
        'embedding_id',
    ];

    protected function casts(): array
    {
        return [
            'content_type' => BusinessInfoContentType::class,
            'embedding_status' => EmbeddingStatus::class,
        ];
    }

    public function aiBot(): BelongsTo
    {
        return $this->belongsTo(AiBot::class, 'ai_bot_id');
    }

    public function isPendingEmbedding(): bool
    {
        return $this->embedding_status === EmbeddingStatus::Pending;
    }

    public function markEmbeddingCompleted(string $embeddingId): void
    {
        $this->forceFill([
            'embedding_status' => EmbeddingStatus::Completed,
            'embedding_id' => $embeddingId,
        ])->save();
    }

    public function markEmbeddingFailed(): void
    {
        $this->forceFill([
            'embedding_status' => EmbeddingStatus::Failed,
        ])->save();
    }
}
