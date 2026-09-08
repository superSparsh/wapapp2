<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\TwoFactorAuthenticatable;
use App\Enums\RecordStatus;
use App\Enums\TeamMemberRole;
use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\HasTwoFactorAuthentication;
use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class TeamMember extends Authenticatable implements TwoFactorAuthenticatable
{
    use HasFactory;
    use HasPublicUuid;
    use HasTwoFactorAuthentication;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'parent_user_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'role',
        'status',
        'auto_assign_chats',
        'assigned_whatsapp_line_ids',
        'permissions',
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
            'password' => 'hashed',
            'role' => TeamMemberRole::class,
            'status' => RecordStatus::class,
            'auto_assign_chats' => 'boolean',
            'assigned_whatsapp_line_ids' => 'array',
            'permissions' => 'array',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getEmailForPasswordReset(): string
    {
        return $this->email;
    }

    public function parentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function assignedConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'assigned_team_member_id');
    }

    public function managedAssignments(): HasMany
    {
        return $this->hasMany(ManagerMemberAssignment::class, 'manager_id');
    }

    public function managerAssignment(): HasMany
    {
        return $this->hasMany(ManagerMemberAssignment::class, 'member_id');
    }

    public function displayName(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? '')) ?: $this->email;
    }

    public function isManager(): bool
    {
        return $this->role === TeamMemberRole::Manager;
    }

    public function isActive(): bool
    {
        return $this->status === RecordStatus::Active;
    }
}
