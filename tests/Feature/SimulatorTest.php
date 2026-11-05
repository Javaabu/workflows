<?php

namespace Javaabu\Workflows\Tests\Feature;

use Javaabu\Workflows\Definitions\Step;
use Javaabu\Workflows\Definitions\Transition;
use Javaabu\Workflows\Engine\Simulation;
use Javaabu\Workflows\Engine\Simulator;
use Javaabu\Workflows\Facades\Workflows;
use Javaabu\Workflows\Models\WorkflowHistory;
use Javaabu\Workflows\Models\WorkflowInstance;
use Javaabu\Workflows\Tests\TestCase;
use Javaabu\Workflows\Tests\TestSupport\Workflows\LeaveRequestWorkflow;
use PHPUnit\Framework\Attributes\Test;

class SimulatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        LeaveRequestWorkflow::definition()->sync();
    }

    #[Test]
    public function it_stops_at_a_task_without_a_choice(): void
    {
        $run = Workflows::simulate('leave-request', subject: ['days' => 3]);

        $this->assertTrue($run->waiting());
        $this->assertSame(['start', 'review'], $run->steps());
        $this->assertSame(['approve', 'send_back', 'reject'], array_column($run->options, 'key'));
        $this->assertSame('Waiting at Manager review: choose what happens there.', $run->message);
    }

    #[Test]
    public function it_follows_choices_and_decisions(): void
    {
        $short = Workflows::simulate('leave-request', subject: ['days' => 3], choices: ['review' => 'approve']);

        $this->assertTrue($short->completed());
        $this->assertSame('approved', $short->outcome);
        $this->assertSame(['start', 'review', 'long_leave', 'approved'], $short->steps());
        $this->assertSame(['submit', 'approve', 'short'], $short->transitions());
        $this->assertSame('default', $short->path[2]['reason']);

        $long = Workflows::simulate('leave-request', subject: ['days' => 10], choices: ['review' => 'approve', 'hr_review' => 'hr_reject']);

        $this->assertSame(['start', 'review', 'long_leave', 'hr_review', 'rejected'], $long->steps());
        $this->assertSame('conditions', $long->path[2]['reason']);
        $this->assertSame('rejected', $long->outcome);
    }

    #[Test]
    public function it_takes_one_choice_per_visit(): void
    {
        $run = Workflows::simulate('leave-request', subject: ['days' => 1], choices: [
            'review' => ['send_back', 'send_back', 'approve'],
            'revise' => 'resubmit',
        ]);

        $this->assertSame(['start', 'review', 'revise', 'review', 'revise', 'review', 'long_leave', 'approved'], $run->steps());
    }

    #[Test]
    public function it_reports_impossible_choices(): void
    {
        $run = Workflows::simulate('leave-request', choices: ['review' => 'hr_approve']);

        $this->assertSame(Simulation::STUCK, $run->status);
        $this->assertSame('review', $run->lastStep());
    }

    #[Test]
    public function it_describes_actions_and_applies_set_data_without_running_anything(): void
    {
        Workflows::define('sim')->steps([
            Step::start()->runs('set_data', ['values' => ['fast' => true]])->runs('webhook', ['url' => 'https://example.com/{{ days }}']),
            Step::decision('which'),
            Step::end('fast'),
            Step::end('slow')->runs('update_model', ['attributes' => ['status' => 'slow']]),
        ])->transitions([
            Transition::make('go')->from('start')->to('which'),
            Transition::make('to_fast')->from('which')->to('fast')->when('fast', true),
            Transition::make('to_slow')->from('which')->to('slow'),
        ])->sync();

        $run = Workflows::simulate('sim', data: ['days' => 2]);

        $this->assertSame('fast', $run->outcome);
        $this->assertSame([
            ['action' => 'set_data', 'description' => 'Set fast = true'],
            ['action' => 'webhook', 'description' => 'Call POST https://example.com/2'],
        ], $run->path[0]['actions']);
        $this->assertSame(['days' => 2, 'fast' => true], $run->data);

        $this->assertSame(0, WorkflowInstance::count());
        $this->assertSame(0, WorkflowHistory::count());
    }

    #[Test]
    public function it_tests_the_draft(): void
    {
        LeaveRequestWorkflow::definition()
            ->step(Step::end('withdrawn'))
            ->transition(Transition::make('withdraw')->from('review')->to('withdrawn'))
            ->sync(publish: false);

        $this->assertSame(Simulation::STUCK, Workflows::simulate('leave-request', choices: ['review' => 'withdraw'])->status);
        $this->assertSame('withdrawn', Workflows::simulate('leave-request', choices: ['review' => 'withdraw'], draft: true)->outcome);
    }

    #[Test]
    public function it_catches_loops(): void
    {
        $run = app(Simulator::class)->simulate('leave-request', choices: ['review' => array_fill(0, 20, 'send_back'), 'revise' => array_fill(0, 20, 'resubmit')], limit: 10);

        $this->assertSame(Simulation::LOOPED, $run->status);
        $this->assertArrayHasKey('path', $run->toArray());
    }
}
