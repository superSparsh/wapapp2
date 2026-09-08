<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappFlowSubmission extends Model
{
    use HasFactory;
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'whatsapp_flow_id',
        'conversation_id',
        'contact_phone',
        'form_data',
        'status',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'form_data' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    // ─── Relationships ─────────────────────────────────────────────

    public function whatsappFlow(): BelongsTo
    {
        return $this->belongsTo(WhatsappFlow::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    // ─── Helpers ───────────────────────────────────────────────────

    public function isProcessed(): bool
    {
        return $this->status === 'processed';
    }

    public function markProcessed(): void
    {
        $this->update([
            'status' => 'processed',
            'processed_at' => now(),
        ]);
    }

    public function markFailed(): void
    {
        $this->update([
            'status' => 'failed',
            'processed_at' => now(),
        ]);
    }
}
