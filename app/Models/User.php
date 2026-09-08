<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\TwoFactorAuthenticatable;
use App\Enums\UserRole;
use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\HasTwoFactorAuthentication;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name', 'first_name', 'last_name', 'email', 'phone', 'password', 'role', 'is_active',
    'avatar_path', 'api_token', 'last_login_at',
    'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
])]
#[Hidden(['password', 'remember_token', 'api_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements MustVerifyEmail, TwoFactorAuthenticatable
{
    use HasFactory;
    use HasPublicUuid;
    use HasTwoFactorAuthentication;
    use Notifiable;
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(TeamMember::class, 'parent_user_id');
    }

    public function assignedConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'assigned_user_id');
    }
}
