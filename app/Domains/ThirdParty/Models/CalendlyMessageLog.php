<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Models;

use App\Domains\ThirdParty\Enums\MessageLogStatus;
use App\Models\TenantModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendlyMessageLog extends TenantModel
{
    protected $table = 'calendly_message_logs';

    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'event_id',
        'recipient_type',
        'recipient_number',
        'invitee_email',
        'event_name',
        'event_type',
        'status',
        'error_message',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'status'  => MessageLogStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(CalendlyEvent::class, 'event_id');
    }
}
