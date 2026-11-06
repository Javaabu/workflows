<?php

namespace Javaabu\Workflows\Tests\Http;

use Javaabu\Translatable\Facades\Languages;
use Javaabu\Translatable\Translatable;
use Javaabu\Workflows\Facades\Workflows;
use Javaabu\Workflows\Models\Translatable\Workflow as TranslatableWorkflow;
use Javaabu\Workflows\Models\Workflow;
use Javaabu\Workflows\Support\Translations;
use Javaabu\Workflows\Tests\Concerns\InteractsWithTranslatable;
use Javaabu\Workflows\Tests\TestCase;
use Javaabu\Workflows\Tests\TestSupport\Workflows\LeaveRequestWorkflow;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\Attributes\Test;

#[RequiresMethod(Translatable::class, 'enabled')]
class TranslatableTest extends TestCase
{
    use InteractsWithTranslatable;
    use InteractsWithWorkflows;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpWorkflows();
        $this->setUpLanguages(['en', 'dv']);
        Workflows::forgetModels();
    }

    #[Test]
    public function it_uses_the_translatable_workflow_model(): void
    {
        $this->assertTrue(Translations::enabled());
        $this->assertSame(TranslatableWorkflow::class, Workflows::model('workflow'));
        $this->assertSame(['en', 'dv'], Translations::locales());
    }

    #[Test]
    public function synced_workflows_keep_their_name_in_every_language(): void
    {
        $workflow = LeaveRequestWorkflow::definition()->sync();

        $this->assertInstanceOf(TranslatableWorkflow::class, $workflow);
        $this->assertSame('Leave request', $workflow->translate('name', 'en'));
        $this->assertSame('ޗުއްޓީގެ އެދުން', $workflow->translate('name', 'dv'));
        $this->assertSame(['en' => 'Leave request', 'dv' => 'ޗުއްޓީގެ އެދުން'], $workflow->definition()->getName());

        // Morphs still point at the base model
        $this->assertSame(Workflow::class, $workflow->getMorphClass());
    }

    #[Test]
    public function steps_and_transitions_read_in_the_current_language(): void
    {
        LeaveRequestWorkflow::definition()->sync();
        $instance = Workflows::start('leave-request', subject: $this->leaveRequest(), by: $this->makeUser());

        Languages::setCurrentLocale('dv');
        app()->setLocale('dv');

        $this->assertSame('މެނޭޖަރުގެ ރިވިއު', Workflows::text($instance->currentStep()->getName()));
        $this->assertSame('ގަބޫލުކުރޭ', Workflows::text($instance->definition()->getTransition('approve')->getLabel()));
        $this->assertSame('Send back', Workflows::text($instance->definition()->getTransition('send_back')->getLabel()));
    }

    #[Test]
    public function the_settings_and_builder_ask_for_every_language(): void
    {
        LeaveRequestWorkflow::definition()->sync();

        $this->actingAs($this->editor())->get('/workflows/leave-request/edit')
            ->assertOk()
            ->assertSee('name="name[en]"', false)
            ->assertSee('name="name[dv]"', false)
            ->assertSee('ޗުއްޓީގެ އެދުން');

        $this->actingAs($this->editor())->getJson('/workflows/leave-request')
            ->assertJsonPath('builder.catalog.locales.1.code', 'dv')
            ->assertJsonPath('builder.catalog.locales.1.rtl', true);

        $this->actingAs($this->editor())
            ->patch('/workflows/leave-request', ['key' => 'leave-request', 'name' => ['en' => 'Leave', 'dv' => 'ޗުއްޓީ'], 'is_active' => '1'])
            ->assertRedirect();

        $workflow = Workflows::workflow('leave-request');
        $this->assertSame('Leave', $workflow->translate('name', 'en'));
        $this->assertSame('ޗުއްޓީ', $workflow->translate('name', 'dv'));
    }

    #[Test]
    public function it_works_with_translatable_turned_off(): void
    {
        $this->withoutTranslatable(function () {
            Workflows::forgetModels();

            $this->assertFalse(Translations::enabled());
            $this->assertSame(Workflow::class, Workflows::model('workflow'));

            $workflow = LeaveRequestWorkflow::definition()->sync();

            $this->assertSame('Leave request', $workflow->name);
            $this->assertSame('Leave request', $workflow->definition()->getName());
        });
    }
}
