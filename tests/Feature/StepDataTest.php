<?php

namespace Javaabu\Workflows\Tests\Feature;

use Illuminate\Validation\ValidationException;
use Javaabu\FormBuilder\Definitions\Field;
use Javaabu\FormBuilder\Definitions\Section;
use Javaabu\FormBuilder\Facades\FormBuilder;
use Javaabu\FormBuilder\Models\FormEntry;
use Javaabu\Workflows\Definitions\Assignee;
use Javaabu\Workflows\Definitions\Step;
use Javaabu\Workflows\Definitions\Transition;
use Javaabu\Workflows\Engine\StepForms;
use Javaabu\Workflows\Facades\Workflows;
use Javaabu\Workflows\Models\WorkflowInstance;
use Javaabu\Workflows\Models\WorkflowTask;
use Javaabu\Workflows\Tests\TestCase;
use Javaabu\Workflows\Tests\TestSupport\Forms\LeaveForms;
use Javaabu\Workflows\Tests\TestSupport\Models\LeaveRequest;
use PHPUnit\Framework\Attributes\Test;

class StepDataTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        LeaveForms::request();
        LeaveForms::review();

        Workflows::define('leave')
            ->for(LeaveRequest::class)
            ->steps([
                Step::start()->form('leave-request'),
                Step::task('review')->assignTo(Assignee::subject('manager'))->form('leave-review'),
                Step::decision('check'),
                Step::end('approved'),
                Step::end('long'),
                Step::end('rejected'),
            ])
            ->transitions([
                Transition::make('submit')->from('start')->to('review'),
                Transition::make('approve')->from('review')->to('check')->label('Approve'),
                Transition::make('reject')->from('review')->to('rejected')->label('Reject')->skipForm(),
                Transition::make('is_long')->from('check')->to('long')->when('approved_days', '>', 5)->when('leave_type', 'annual'),
                Transition::make('is_short')->from('check')->to('approved'),
            ])
            ->sync();
    }

    #[Test]
    public function the_start_form_validates_and_fills_the_data(): void
    {
        $employee = $this->makeUser();
        $leave = $this->leaveRequest();

        try {
            Workflows::start('leave', subject: $leave, by: $employee, data: ['days' => 'many']);
            $this->fail('The start form wasn\'t validated.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('leave_type', $exception->errors());
            $this->assertSame(0, WorkflowInstance::count());
        }

        $instance = Workflows::start('leave', subject: $leave, by: $employee, data: ['leave_type' => 'annual', 'days' => 7, 'reason' => 'ދަތުރު']);

        $this->assertSame(['leave_type' => 'annual', 'days' => 7, 'reason' => 'ދަތުރު'], array_intersect_key($instance->data, array_flip(['leave_type', 'days', 'reason'])));

        $entry = FormEntry::forSubject($instance)->first();
        $this->assertNotNull($entry);
        $this->assertTrue($entry->submitter->is($employee));
        $this->assertSame($entry->getKey(), $instance->history()->where('event', 'started')->first()->data['form_entry_id']);
    }

    #[Test]
    public function a_tasks_form_is_stored_with_the_task_and_drives_decisions(): void
    {
        $manager = $this->makeUser('މެނޭޖަރު');
        $instance = Workflows::start('leave', subject: $this->leaveRequest(7, $manager), by: $this->makeUser(), data: ['leave_type' => 'annual', 'days' => 7]);

        try {
            $instance->transition('approve', by: $manager, data: []);
            $this->fail('The review form wasn\'t validated.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('approved_days', $exception->errors());
            $this->assertSame('review', $instance->refresh()->current_step);
        }

        $instance->transition('approve', by: $manager, data: ['approved_days' => 6, 'note' => 'ރަނގަޅު']);

        $this->assertSame('long', $instance->outcome);
        $this->assertSame(6, $instance->answer('approved_days'));

        $task = WorkflowTask::query()->where('step', 'review')->first();
        $this->assertNotNull($task->form_entry_id);
        $this->assertSame('ރަނގަޅު', $task->entry->answer('note'));
        $this->assertTrue($task->entry->subject->is($task));
    }

    #[Test]
    public function a_transition_can_skip_the_form(): void
    {
        $manager = $this->makeUser('މެނޭޖަރު');
        $instance = Workflows::start('leave', subject: $this->leaveRequest(2, $manager), by: $this->makeUser(), data: ['leave_type' => 'sick', 'days' => 2]);

        $instance->transition('reject', by: $manager, comment: 'ނޫން');

        $this->assertSame('rejected', $instance->outcome);
        $this->assertNull($instance->tasks()->first()->form_entry_id);
    }

    #[Test]
    public function a_step_can_pin_a_form_version(): void
    {
        FormBuilder::define('leave-review')->sections([
            Section::make('main')->fields([Field::text('verdict')->label('Verdict')->required()]),
        ])->sync();

        $forms = app(StepForms::class);

        $this->assertSame(2, $forms->version(Step::task('x')->form('leave-review'))->number);
        $this->assertSame(1, $forms->version(Step::task('x')->form('leave-review', 1))->number);
        $this->assertNull($forms->version(Step::task('x')));

        $stencil = $forms->stencil(Step::task('x')->form('leave-review', 1), '/submit', ['approved_days' => 3]);
        $this->assertStringContainsString('approved_days', $stencil->toHtml());
    }

    #[Test]
    public function steps_must_name_forms_that_exist(): void
    {
        $this->expectException(ValidationException::class);

        Workflows::define('bad')->steps([
            Step::start()->form('no-such-form'),
            Step::end('done'),
        ])->transitions([Transition::make('go')->from('start')->to('done')])->sync();
    }
}
