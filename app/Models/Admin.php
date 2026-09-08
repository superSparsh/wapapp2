<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\TwoFactorAuthenticatable;
use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\HasTwoFactorAuthentication;
use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Admin extends Authenticatable implements TwoFactorAuthenticatable
{
    use HasPublicUuid;
    use HasTwoFactorAuthentication;
    use SoftDeletes;
    use UsesCentralConnection;

    protected $fillable = [
        'admin_role_id',
        'name',
        'email',
        'password',
        'is_active',
        'last_login_at',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function adminRole(): BelongsTo
    {
        return $this->belongsTo(AdminRole::class);
    }
}
