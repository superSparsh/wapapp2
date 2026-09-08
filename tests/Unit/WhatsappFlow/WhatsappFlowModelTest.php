<?php

declare(strict_types=1);

namespace Tests\Unit\WhatsappFlow;

use App\Enums\WhatsappFlowStatus;
use App\Enums\WhatsappFlowSubmitAction;
use App\Models\WhatsappFlow;
use App\Models\WhatsappFlowSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class WhatsappFlowModelTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_status_cast_works(): void
    {
        $flow = WhatsappFlow::factory()->create(['status' => WhatsappFlowStatus::Draft]);

        $this->assertInstanceOf(WhatsappFlowStatus::class, $flow->status);
        $this->assertSame('draft', $flow->status->value);
    }

    public function test_on_submit_action_cast_works(): void
    {
        $flow = WhatsappFlow::factory()->create(['on_submit_action' => WhatsappFlowSubmitAction::CreateLead]);

        $this->assertInstanceOf(WhatsappFlowSubmitAction::class, $flow->on_submit_action);
        $this->assertSame('create_lead', $flow->on_submit_action->value);
    }

    public function test_is_active_helper(): void
    {
        $draft = WhatsappFlow::factory()->create();
        $active = WhatsappFlow::factory()->active()->create();

        $this->assertFalse($draft->isActive());
        $this->assertTrue($active->isActive());
    }

    public function test_screen_count_returns_correct_value(): void
    {
        $flow = WhatsappFlow::factory()->withFlowJson(3, 2)->create();

        $this->assertSame(3, $flow->screenCount());
    }

    public function test_field_count_returns_total_fields(): void
    {
        $flow = WhatsappFlow::factory()->withFlowJson(2, 4)->create();

        $this->assertSame(8, $flow->fieldCount());
    }

    public function test_screen_count_returns_zero_for_null_json(): void
    {
        $flow = WhatsappFlow::factory()->create(['flow_json' => null]);

        $this->assertSame(0, $flow->screenCount());
        $this->assertSame(0, $flow->fieldCount());
    }

    public function test_submissions_relationship(): void
    {
        $flow = WhatsappFlow::factory()->withSubmissions(3)->create();

        $this->assertCount(3, $flow->submissions);
        $this->assertSame(3, $flow->submissionCount());
    }

    public function test_active_scope_filters_correctly(): void
    {
        WhatsappFlow::factory()->count(2)->create();
        WhatsappFlow::factory()->active()->count(3)->create();
        WhatsappFlow::factory()->archived()->create();

        $this->assertCount(3, WhatsappFlow::query()->active()->get());
        $this->assertCount(2, WhatsappFlow::query()->draft()->get());
        $this->assertCount(1, WhatsappFlow::query()->archived()->get());
    }

    public function test_is_published_helper(): void
    {
        $unpublished = WhatsappFlow::factory()->create();
        $published = WhatsappFlow::factory()->active()->create();

        $this->assertFalse($unpublished->isPublished());
        $this->assertTrue($published->isPublished());
    }

    public function test_flow_json_cast_to_array(): void
    {
        $json = ['screens' => [['id' => 's1']], 'first_screen' => 's1'];
        $flow = WhatsappFlow::factory()->create(['flow_json' => $json]);

        $flow->refresh();
        $this->assertIsArray($flow->flow_json);
        $this->assertSame('s1', $flow->flow_json['first_screen']);
    }
}
