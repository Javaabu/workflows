<?php

namespace Javaabu\Workflows\Tests\TestSupport\Workflows;

use Javaabu\Workflows\Definitions\Assignee;
use Javaabu\Workflows\Definitions\Step;
use Javaabu\Workflows\Definitions\Transition;
use Javaabu\Workflows\Definitions\WorkflowDefinition;
use Javaabu\Workflows\Tests\TestSupport\Models\LeaveRequest;

/**
 * A leave request: the manager reviews it; long leave also goes to HR
 */
class LeaveRequestWorkflow
{
    public static function definition(): WorkflowDefinition
    {
        return WorkflowDefinition::make('leave-request')
            ->name(['en' => 'Leave request', 'dv' => 'ޗުއްޓީގެ އެދުން'])
            ->for(LeaveRequest::class)
            ->steps([
                Step::start()->name('Submitted')->position(40, 120),
                Step::task('review')->name(['en' => 'Manager review', 'dv' => 'މެނޭޖަރުގެ ރިވިއު'])->assignTo(Assignee::subject('manager'))->dueIn(48)->position(240, 120),
                Step::decision('long_leave')->name('Long leave?')->position(440, 120),
                Step::task('hr_review')->name('HR review')->assignTo(Assignee::role('hr'))->position(640, 40),
                Step::task('revise')->name('Revise')->assignTo(Assignee::starter())->position(240, 280),
                Step::end('approved')->name('Approved')->color('success')->position(840, 120),
                Step::end('rejected')->name('Rejected')->color('danger')->position(440, 280),
            ])
            ->transitions([
                Transition::make('submit')->from('start')->to('review'),
                Transition::make('approve')->from('review')->to('long_leave')->label(['en' => 'Approve', 'dv' => 'ގަބޫލުކުރޭ'])->color('success'),
                Transition::make('send_back')->from('review')->to('revise')->label('Send back')->requireComment(),
                Transition::make('reject')->from('review')->to('rejected')->label('Reject')->color('danger')->requireComment(),
                Transition::make('resubmit')->from('revise')->to('review')->label('Resubmit'),
                Transition::make('long')->from('long_leave')->to('hr_review')->when('subject.days', '>', 5),
                Transition::make('short')->from('long_leave')->to('approved'),
                Transition::make('hr_approve')->from('hr_review')->to('approved')->label('Approve')->color('success'),
                Transition::make('hr_reject')->from('hr_review')->to('rejected')->label('Reject')->color('danger'),
            ]);
    }
}
