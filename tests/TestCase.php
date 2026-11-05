<?php

namespace Javaabu\Workflows\Tests;

use Javaabu\Workflows\WorkflowsServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            WorkflowsServiceProvider::class,
        ];
    }
}
