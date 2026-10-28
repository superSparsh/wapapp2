<?php

declare(strict_types=1);

namespace Tests\Unit\Chatbot;

use App\Domains\Chatbot\Support\TemplateMessageWaitHydrator;
use App\Enums\ChatbotFlowStateStatus;
use App\Models\ChatbotFlow;
use App\Models\ChatbotFlowState;
use App\Models\Conversation;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TemplateMessageWaitHydratorTest extends TestCase
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

    #[Test]
    public function it_matches_outbound_template_by_code_or_id(): void
    {
        $template = Template::factory()->create([
            'code' => '1234567890123',
            'name' => 'Match Me',
        ]);

        $data = [
            'templateId' => $template->id,
            'templateCode' => '1234567890123',
        ];

        $this->assertTrue(TemplateMessageWaitHydrator::matchesOutboundTemplate($data, '1234567890123', null));
        $this->assertTrue(TemplateMessageWaitHydrator::matchesOutboundTemplate($data, '', $template->id));
        $this->assertFalse(TemplateMessageWaitHydrator::matchesOutboundTemplate($data, '9999999999999', 888));
    }

    #[Test]
    public function it_hydrates_waiting_state_for_quick_replies(): void
    {
        $flow = ChatbotFlow::factory()->create();
        $conversation = Conversation::factory()->create();
        $state = ChatbotFlowState::query()->create([
            'conversation_id' => $conversation->id,
            'chatbot_flow_id' => $flow->id,
            'current_node_id' => 'template_1',
            'variables' => [],
            'status' => ChatbotFlowStateStatus::Active,
            'expires_at' => now()->addHour(),
        ]);

        $node = [
            'id' => 'template_1',
            'class' => 'templateMessage',
            'data' => [
                'quickReplies' => [['text' => 'Yes'], ['text' => 'No']],
            ],
            'outputs' => [],
        ];

        $this->assertTrue(TemplateMessageWaitHydrator::isWaitable($node));
        TemplateMessageWaitHydrator::hydrateWaitingState($state, $node);
        $state->refresh();

        $this->assertSame(ChatbotFlowStateStatus::Waiting, $state->status);
        $this->assertSame('template_1', $state->current_node_id);
        $this->assertTrue((bool) ($state->variables['_template_entry'] ?? false));
        $this->assertCount(2, $state->variables['_quick_replies'] ?? []);
    }
}
