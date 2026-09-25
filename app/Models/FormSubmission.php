<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormSubmission extends Model
{
    use HasPublicUuid;
    protected $fillable = [
        'signup_form_id',
        'contact_id',
        'template_id',
        'phone',
        'submission_data',
        'message_status',
        'external_message_id',
        'outbound_message_id',
        'failed_reason',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'submission_data' => 'array',
            'outbound_message_id' => 'integer',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function signupForm(): BelongsTo
    {
        return $this->belongsTo(SignupForm::class, 'signup_form_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function outboundMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'outbound_message_id');
    }

    /**
     * Display status prefers timestamps (legacy conversations parity).
     */
    public function displayStatus(): string
    {
        if ($this->read_at !== null) {
            return 'read';
        }
        if ($this->delivered_at !== null) {
            return 'delivered';
        }
        if ($this->failed_at !== null || $this->message_status === 'failed') {
            return 'failed';
        }
        if ($this->sent_at !== null || $this->message_status === 'sent') {
            return 'sent';
        }

        return (string) ($this->message_status ?: 'pending');
    }
}
