<?php

namespace Tests\Unit\WhatsappFlow;

use App\Domains\WhatsappFlow\Services\WhatsappFlowInteractiveService;
use App\Models\WhatsappFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class WhatsappFlowInteractiveServiceTest extends TestCase
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

    public function test_resolves_template_button_flow_to_meta_id_and_first_screen(): void
    {
        $flow = WhatsappFlow::factory()->active()->withFlowJson()->create([
            'meta_flow_id' => 'flow_meta_123',
            'meta_json' => [
                'screens' => [
                    ['id' => 'WELCOME_SCREEN'],
                ],
            ],
        ]);

        $service = app(WhatsappFlowInteractiveService::class);
        $resolved = $service->resolveTemplateButtonFlow((string) $flow->id);

        $this->assertNotNull($resolved);
        $this->assertSame('flow_meta_123', $resolved['flow_id']);
        $this->assertSame('WELCOME_SCREEN', $resolved['navigate_screen']);
    }

    public function test_builds_flow_interactive_payload(): void
    {
        $flow = WhatsappFlow::factory()->active()->create([
            'meta_flow_id' => 'flow_meta_456',
            'meta_json' => [
                'screens' => [
                    ['id' => 'START'],
                ],
            ],
        ]);

        $service = app(WhatsappFlowInteractiveService::class);
        $content = $service->buildFlowInteractiveContent($flow, 'Please continue', 'Open Flow', 'token_abc');

        $this->assertSame('flow', $content['type']);
        $this->assertSame('Please continue', $content['body']['text']);
        $this->assertSame('flow', $content['action']['name']);
        $this->assertSame('flow_meta_456', $content['action']['parameters']['flow_id']);
        $this->assertSame('token_abc', $content['action']['parameters']['flow_token']);
        $this->assertSame('Open Flow', $content['action']['parameters']['flow_cta']);
        $this->assertSame('START', $content['action']['parameters']['flow_action_payload']['screen']);
    }

    public function test_legacy_flow_list_uses_meta_flow_id_shape(): void
    {
        WhatsappFlow::factory()->active()->create([
            'name' => 'Checkout Flow',
            'meta_flow_id' => 'flow_meta_789',
            'categories' => ['SHOPPING'],
        ]);

        $service = app(WhatsappFlowInteractiveService::class);
        $flows = $service->legacyFlowList();

        $this->assertCount(1, $flows);
        $this->assertSame('flow_meta_789', $flows[0]['flowId']);
        $this->assertSame('Checkout Flow', $flows[0]['flowName']);
        $this->assertSame(['SHOPPING'], $flows[0]['categories']);
    }
}
