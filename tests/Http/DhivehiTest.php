<?php

namespace Javaabu\Workflows\Tests\Http;

use Javaabu\Workflows\Facades\Workflows;
use Javaabu\Workflows\Tests\TestCase;
use Javaabu\Workflows\Tests\TestSupport\Workflows\LeaveRequestWorkflow;
use PHPUnit\Framework\Attributes\Test;

class DhivehiTest extends TestCase
{
    use InteractsWithWorkflows;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpWorkflows();
        $this->app->setLocale('dv');
        LeaveRequestWorkflow::definition()->sync();
    }

    #[Test]
    public function the_pages_are_in_dhivehi_and_right_to_left(): void
    {
        $this->actingAs($this->editor())->get('/workflows')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('ވޯކްފްލޯތައް')
            ->assertSee('އައު ވޯކްފްލޯއެއް');

        $this->actingAs($this->editor())->getJson('/workflows/leave-request')
            ->assertJsonPath('strings.add_step', 'ފިޔަވަޅެއް އިތުރުކުރޭ')
            ->assertJsonPath('builder.catalog.step_types.2.label', 'ނިންމުން')
            ->assertJsonPath('builder.catalog.operators.2.label', 'ވުރެ ގިނަ');
    }

    #[Test]
    public function messages_and_history_are_in_dhivehi(): void
    {
        $manager = $this->makeUser('މެނޭޖަރު');
        $instance = Workflows::start('leave-request', subject: $this->leaveRequest(2, $manager), by: $this->makeUser('އަޙްމަދު'));

        $this->actingAs($manager)
            ->post("/tasks/{$instance->getKey()}/transition", ['transition' => 'reject'])
            ->assertSessionHasErrors(['comment' => 'Reject ކުރުމަށް ކޮމެންޓެއް ލިޔުއްވާ.']);

        $this->actingAs($manager)
            ->post("/tasks/{$instance->getKey()}/transition", ['transition' => 'approve', 'comment' => 'ރަނގަޅު'])
            ->assertSessionHas('status', 'ނިމިއްޖެ: ގަބޫލުކުރޭ.');

        $this->actingAs($manager)->get("/tasks/{$instance->getKey()}")
            ->assertSee('ތާރީޚު')
            ->assertSee('ގަބޫލުކުރޭ')
            ->assertSee('ރަނގަޅު');
    }

    #[Test]
    public function the_tester_explains_in_dhivehi(): void
    {
        $run = Workflows::simulate('leave-request');

        $this->assertSame('މެނޭޖަރުގެ ރިވިއު ގައި މަޑުކުރަނީ: އެތަނުގައި ވާނެ ގޮތް ހޮވާ.', $run->message);
    }
}
