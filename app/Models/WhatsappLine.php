<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;

class WhatsappLine extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'phone',
        'display_name',
        'waba_id',
        'alibaba_cust_space_id',
        'alibaba_phone_number_id',
        'status',
        'is_default',
        'line_password',
        'quality_rating',
        'messaging_limit_tier',
        'profile',
        'metadata',
    ];

    protected $hidden = ['line_password'];

    protected function casts(): array
    {
        return [
            'status'    => RecordStatus::class,
            'is_default' => 'boolean',
            'profile'   => 'array',
            'metadata'  => 'array',
        ];
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Whether a Number Access password has been set for this line.
     */
    public function hasLinePassword(): bool
    {
        return filled($this->line_password);
    }

    /**
     * Check if the given plain-text password matches this line's stored hash.
     */
    public function checkLinePassword(string $password): bool
    {
        return $this->hasLinePassword()
            && Hash::check($password, (string) $this->line_password);
    }

    /**
     * Whether the line is actively connected (has a WABA ID).
     */
    public function isConnected(): bool
    {
        return filled($this->waba_id);
    }

    /**
     * Format the phone number for display (e.g., +91 98765 43210).
     */
    public function displayPhone(): string
    {
        $digits = preg_replace('/\D/', '', (string) $this->phone) ?? '';

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            return '+91 ' . substr($digits, 2, 5) . ' ' . substr($digits, 7);
        }

        return '+' . $digits;
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function webhookSubscriptions(): HasMany
    {
        return $this->hasMany(WebhookSubscription::class);
    }
}
