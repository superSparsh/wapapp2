<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdminRole extends Model
{
    use HasPublicUuid;
    use SoftDeletes;
    use UsesCentralConnection;

    /** @var list<string> */
    public const PERMISSIONS = [
        'customers',
        'plans',
        'billing',
        'settings',
        'announcements',
        'admins',
        'queues',
        'health',
    ];

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'permissions',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function admins(): HasMany
    {
        return $this->hasMany(Admin::class);
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = is_array($this->permissions) ? $this->permissions : [];

        return in_array($permission, $permissions, true);
    }
}
