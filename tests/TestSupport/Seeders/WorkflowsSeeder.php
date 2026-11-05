<?php

namespace Javaabu\Workflows\Tests\TestSupport\Seeders;

use Illuminate\Database\Seeder;
use Javaabu\Workflows\Tests\TestSupport\Workflows\LeaveRequestWorkflow;

class WorkflowsSeeder extends Seeder
{
    public function run(): void
    {
        LeaveRequestWorkflow::definition()->locked()->sync();
    }
}
