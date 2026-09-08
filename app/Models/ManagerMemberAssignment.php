<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManagerMemberAssignment extends Model
{
    protected $fillable = [
        'parent_user_id',
        'manager_id',
        'member_id',
    ];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'manager_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'member_id');
    }
}
