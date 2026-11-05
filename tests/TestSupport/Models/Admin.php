<?php

namespace Javaabu\Workflows\Tests\TestSupport\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Javaabu\Permissions\Traits\HasRoles;

class Admin extends Authenticatable
{
    use HasRoles;

    protected $guarded = [];

    protected string $guard_name = 'web_admin';
}
