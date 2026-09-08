<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Models;

use App\Models\TenantModel;
use Illuminate\Support\Str;

class WebsiteTracker extends TenantModel
{
    protected $table = 'website_trackers';

    protected $fillable = [
        'name',
        'domain',
        'tracking_token',
        'status',
    ];

    protected static function booted(): void
    {
        static::creating(function (WebsiteTracker $tracker): void {
            if (blank($tracker->tracking_token)) {
                $tracker->tracking_token = Str::random(40);
            }
        });
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
