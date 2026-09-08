<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopifyDomainRegistry extends Model
{
    use UsesCentralConnection;

    protected $table = 'shopify_domain_registry';

    protected $fillable = [
        'shop_domain',
        'tenant_id',
        'user_id',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
