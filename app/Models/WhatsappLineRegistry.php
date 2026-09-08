<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappLineRegistry extends Model
{
    use UsesCentralConnection;

    protected $table = 'whatsapp_line_registry';
    protected $fillable = [
        'phone',
        'tenant_id',
        'line_id',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
