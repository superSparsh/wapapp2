<?php

declare(strict_types=1);

namespace App\Domains\Audience\Models;

use App\Models\Contact;
use App\Models\TenantModel;
use Database\Factories\ContactTagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactTag extends TenantModel
{
    use HasFactory;

    protected static function newFactory(): ContactTagFactory
    {
        return ContactTagFactory::new();
    }

    protected $fillable = [
        'contact_id',
        'name',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }
}
