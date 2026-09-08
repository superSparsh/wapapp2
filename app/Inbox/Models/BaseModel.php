<?php

namespace App\Inbox\Models;

use Illuminate\Database\Eloquent\Model;

abstract class BaseModel extends Model
{
    /**
     * The database connection to use.
     *
     * @var string
     */
    protected $connection = 'inbox';
}
