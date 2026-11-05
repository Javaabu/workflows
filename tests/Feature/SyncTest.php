<?php

namespace Javaabu\Workflows\Tests\Feature;

use Illuminate\Validation\ValidationException;
use Javaabu\Workflows\Definitions\Step;
use Javaabu\Workflows\Definitions\Transition;
use Javaabu\Workflows\Exceptions\WorkflowException;
use Javaabu\Workflows\Facades\Workflows;
use Javaabu\Workflows\Models\WorkflowVersion;
use Javaabu\Workflows\Tests\TestCase;
use Javaabu\Workflows\Tests\TestSupport\Seeders\WorkflowsSeeder;
use Javaabu\Workflows\Tests\TestSupport\Workflows\LeaveRequestWorkflow;
use PHPUnit\Framework\Attributes\Test;

class SyncTest extends TestCase
{
    #[Test]
    public function it_creates_and_publishes_a_workflow(): void
    {
        $workflow = LeaveRequestWorkflow::definition()->sync();

        $this->assertSame('leave-request', $workflow->key);
        $this->assertSame('Leave request', $workflow->name);
        $this->assertSame('leave_request', $workflow->subject_type);
        $this->assertTrue($workflow->isPublished());
        $this->assertTrue($workflow->canStart());
        $this->assertSame(1, $workflow->publishedVersion->number);
        $this->assertFalse($workflow->hasDraft());
        $this->assertEquals(LeaveRequestWorkflow::definition()->structure(), $workflow->publishedVersion->definition);
    }

    #[Test]
    public function syncing_again_changes_nothing(): void
    {
        LeaveRequestWorkflow::definition()->sync();
        LeaveRequestWorkflow::definition()->sync();

        $this->assertSame(1, WorkflowVersion::count());
    }

    #[Test]
    public function a_changed_definition_gets_a_new_version(): void
    {
        LeaveRequestWorkflow::definition()->sync();

        $workflow = LeaveRequestWorkflow::definition()
            ->step(Step::end('withdrawn'))
            ->transition(Transition::make('withdraw')->from('review')->to('withdrawn')->label('ދޫކޮށްލާ'))
            ->sync();

        $this->assertSame(2, $workflow->publishedVersion->number);
        $this->assertSame(2, $workflow->versions()->count());
        $this->assertNotNull($workflow->definition()->getStep('withdrawn'));
    }

    #[Test]
    public function it_can_leave_the_new_version_as_a_draft(): void
    {
        LeaveRequestWorkflow::definition()->sync();

        $workflow = LeaveRequestWorkflow::definition()->step(Step::end('withdrawn'))
            ->transition(Transition::make('withdraw')->from('review')->to('withdrawn'))
            ->sync(publish: false);

        $this->assertSame(1, $workflow->publishedVersion->number);
        $this->assertTrue($workflow->hasDraft());
        $this->assertNotNull($workflow->definition(draft: true)->getStep('withdrawn'));
        $this->assertNull($workflow->definition()->getStep('withdrawn'));

        $workflow->discardDraft();
        $this->assertFalse($workflow->refresh()->hasDraft());
    }

    #[Test]
    public function it_refuses_an_invalid_definition(): void
    {
        $this->expectException(ValidationException::class);

        Workflows::define('broken')->steps([Step::task('lonely')])->sync();
    }

    #[Test]
    public function published_versions_never_change(): void
    {
        $workflow = LeaveRequestWorkflow::definition()->sync();

        $this->expectException(WorkflowException::class);

        $workflow->publishedVersion->update(['definition' => WorkflowVersion::emptyStructure()]);
    }

    #[Test]
    public function seeders_lock_workflows_to_code(): void
    {
        $this->seed(WorkflowsSeeder::class);
        $this->seed(WorkflowsSeeder::class);

        $workflow = Workflows::workflow('leave-request');

        $this->assertTrue($workflow->is_locked);
        $this->assertSame(1, $workflow->versions()->count());

        $this->expectException(WorkflowException::class);
        $workflow->draft();
    }

    #[Test]
    public function a_deleted_workflow_comes_back_when_synced(): void
    {
        LeaveRequestWorkflow::definition()->sync()->delete();

        $workflow = LeaveRequestWorkflow::definition()->sync();

        $this->assertFalse($workflow->trashed());
        $this->assertSame(1, $workflow->versions()->count());
    }
}
