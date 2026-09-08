<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;

class WhatsappFlowExchangeRegistry extends Model
{
    use UsesCentralConnection;

    protected $table = 'whatsapp_flow_exchange_registry';

    protected $fillable = [
        'exchange_token',
        'tenant_id',
        'flow_id',
    ];
}
