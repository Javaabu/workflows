<?php

namespace Javaabu\Workflows\Tests\TestSupport\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Javaabu\Permissions\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
    use Notifiable;

    protected $guarded = [];

    protected string $guard_name = 'web';
}
