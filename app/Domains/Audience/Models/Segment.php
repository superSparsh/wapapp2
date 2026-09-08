<?php

declare(strict_types=1);

namespace App\Domains\Audience\Models;

use App\Models\TenantModel;
use Database\Factories\SegmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\MailList;

class Segment extends TenantModel
{
    use HasFactory;

    protected static function newFactory(): SegmentFactory
    {
        return SegmentFactory::new();
    }

    protected $fillable = [
        'name',
        'mail_list_id',
        'conditions',
        'contact_count',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'contact_count' => 'integer',
        ];
    }

    public function mailList(): BelongsTo
    {
        return $this->belongsTo(MailList::class, 'mail_list_id');
    }
}
