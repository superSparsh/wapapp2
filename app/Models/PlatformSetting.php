<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    use UsesCentralConnection;

    protected $fillable = ['key', 'value'];
}
