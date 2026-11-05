<?php

namespace Javaabu\Workflows\Tests\TestSupport\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Javaabu\Workflows\Tests\TestSupport\Models\Admin;
use Javaabu\Workflows\Tests\TestSupport\Models\LeaveRequest;
use Javaabu\Workflows\Tests\TestSupport\Models\User;

class TestServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database');

        if (is_dir(__DIR__ . '/../views')) {
            View::addLocation(__DIR__ . '/../views');
        }

        Relation::morphMap([
            'user' => User::class,
            'admin' => Admin::class,
            'leave_request' => LeaveRequest::class,
        ]);
    }
}
