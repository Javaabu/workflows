<?php

namespace Javaabu\Workflows\Tests\Http;

use Illuminate\Support\Facades\Gate;
use Javaabu\Permissions\Models\Permission;
use Javaabu\Workflows\Models\Workflow;
use Javaabu\Workflows\Policies\WorkflowPolicy;
use Javaabu\Workflows\Tests\TestCase;
use Javaabu\Workflows\Tests\TestSupport\Http\CustomWorkflowsController;
use Javaabu\Workflows\Tests\TestSupport\Policies\DenyAllPolicy;
use Javaabu\Workflows\Tests\TestSupport\Workflows\LeaveRequestWorkflow;
use PHPUnit\Framework\Attributes\Test;

class AuthorizationTest extends TestCase
{
    use InteractsWithWorkflows;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpWorkflows();
        LeaveRequestWorkflow::definition()->sync();
    }

    #[Test]
    public function it_registers_the_permissions_for_every_guard(): void
    {
        foreach (['web', 'web_admin'] as $guard) {
            foreach (array_keys(WorkflowPolicy::permissions()) as $permission) {
                $this->assertTrue(Permission::query()->where('name', $permission)->where('guard_name', $guard)->exists(), "{$permission} isn't there for {$guard}");
            }
        }
    }

    #[Test]
    public function each_ability_needs_its_permission(): void
    {
        $viewer = $this->editor([WorkflowPolicy::VIEW]);

        $this->actingAs($viewer)->get('/workflows')->assertOk();
        $this->actingAs($viewer)->get('/workflows/leave-request')->assertOk();
        $this->actingAs($viewer)->get('/workflows/leave-request/test')->assertOk();
        $this->actingAs($viewer)->get('/workflows/create')->assertForbidden();
        $this->actingAs($viewer)->putJson('/workflows/leave-request/definition', ['definition' => ['steps' => []]])->assertForbidden();
        $this->actingAs($viewer)->post('/workflows/leave-request/publish')->assertForbidden();
        $this->actingAs($viewer)->delete('/workflows/leave-request')->assertForbidden();
        $this->actingAs($viewer)->get('/workflows/leave-request/instances')->assertForbidden();

        $this->actingAs($this->makeUser())->get('/workflows')->assertForbidden();

        $publisher = $this->editor([WorkflowPolicy::VIEW, WorkflowPolicy::PUBLISH]);
        $this->actingAs($publisher)->getJson('/workflows/leave-request')->assertJsonPath('can.publish', true)->assertJsonPath('can.update', false);
    }

    #[Test]
    public function policies_can_be_overridden(): void
    {
        Gate::policy(Workflow::class, DenyAllPolicy::class);

        $this->actingAs($this->editor())->get('/workflows')->assertForbidden();
    }

    #[Test]
    public function controllers_can_be_overridden_from_config(): void
    {
        config(['workflows.controllers.workflows' => CustomWorkflowsController::class]);

        $this->app['router']->middleware('web')->group(fn ($router) => $router->workflows(['prefix' => 'custom', 'name' => 'custom']));

        $this->actingAs($this->editor())->get('/custom')->assertExactJson(['custom' => true]);
    }

    #[Test]
    public function controllers_can_be_overridden_per_route_group(): void
    {
        $this->app['router']->middleware('web')->group(fn ($router) => $router->workflows(['prefix' => 'other', 'name' => 'other', 'controllers' => ['workflows' => CustomWorkflowsController::class]]));

        $this->actingAs($this->editor())->get('/other')->assertExactJson(['custom' => true]);
        $this->actingAs($this->editor())->get('/workflows')->assertOk()->assertSee('Leave request');
    }
}
