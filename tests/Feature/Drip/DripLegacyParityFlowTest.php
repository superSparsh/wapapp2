<?php

declare(strict_types=1);

namespace Tests\Feature\Drip;

use App\Domains\Drip\Jobs\ExecuteDripStepJob;
use App\Domains\Drip\Services\DripContactOperationService;
use App\Domains\Drip\Services\DripFlowEngine;
use App\Enums\ChatbotFlowStateStatus;
use App\Enums\ChatbotFlowStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\DripCampaign;
use App\Models\DripCampaignState;
use App\Models\MailList;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class DripLegacyParityFlowTest extends TestCase
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

    public function test_save_flow_with_contact_operation_nodes(): void
    {
        $campaign = DripCampaign::factory()->create();
        $mailList = MailList::factory()->create();

        $nodes = [
            [
                'id' => 'node_tag',
                'type' => 'contactOperation',
                'data' => [
                    'label' => 'Tag Lead',
                    'operation_type' => 'tag',
                    'tags' => ['vip', 'interested'],
                ],
            ],
            [
                'id' => 'node_copy',
                'type' => 'contactOperation',
                'data' => [
                    'label' => 'Copy to List',
                    'operation_type' => 'copy',
                    'mail_list_id' => (string) $mailList->id,
                ],
            ],
            [
                'id' => 'node_update',
                'type' => 'contactOperation',
                'data' => [
                    'label' => 'Update Field',
                    'operation_type' => 'update',
                    'field_name' => 'lead_score',
                    'field_value' => '100',
                ],
            ],
        ];

        $this->actingAsTenantUser()
            ->postJson(route('automation.drip.flow.save', $campaign), [
                'nodes' => $nodes,
                'edges' => [],
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'node_count' => 3,
            ]);

        $campaign->refresh();
        $this->assertCount(3, $campaign->exported_data['nodes']);
        $this->assertSame('tag', $campaign->exported_data['nodes'][0]['data']['operation_type']);
        $this->assertSame(['vip', 'interested'], $campaign->exported_data['nodes'][0]['data']['tags']);
    }

    public function test_save_flow_with_condition_nodes(): void
    {
        $campaign = DripCampaign::factory()->create();

        $nodes = [
            [
                'id' => 'node_cond_read',
                'type' => 'condition',
                'data' => [
                    'label' => 'Check Read Status',
                    'condition_type' => 'whatsapp_read',
                    'target_template' => 'welcome_promo',
                    'condition_wait' => '1 day',
                ],
            ],
            [
                'id' => 'node_cond_custom',
                'type' => 'condition',
                'data' => [
                    'label' => 'Check City',
                    'condition_type' => 'custom_variable',
                    'condition_variable' => 'city',
                    'condition_operator' => 'equals',
                    'condition_value' => 'Mumbai',
                ],
            ],
        ];

        $this->actingAsTenantUser()
            ->postJson(route('automation.drip.flow.save', $campaign), [
                'nodes' => $nodes,
                'edges' => [],
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'node_count' => 2,
            ]);
    }

    public function test_engine_executes_contact_operations(): void
    {
        $campaign = DripCampaign::factory()->create([
            'status' => ChatbotFlowStatus::Active,
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'step_1',
                        'type' => 'contactOperation',
                        'data' => [
                            'operation_type' => 'tag',
                            'tags' => ['vip_lead', 'hot'],
                        ],
                    ],
                    [
                        'id' => 'step_2',
                        'type' => 'contactOperation',
                        'data' => [
                            'operation_type' => 'update',
                            'field_name' => 'lead_score',
                            'field_value' => '95',
                        ],
                    ],
                ],
                'edges' => [
                    [
                        'source' => 'step_1',
                        'target' => 'step_2',
                    ],
                ],
            ],
        ]);

        $contact = Contact::factory()->create();
        $conversation = Conversation::factory()->create(['contact_id' => $contact->id]);

        $state = DripCampaignState::create([
            'drip_campaign_id' => $campaign->id,
            'conversation_id' => $conversation->id,
            'current_node_id' => 'step_1',
            'status' => ChatbotFlowStateStatus::Active,
        ]);

        $engine = app(DripFlowEngine::class);
        $engine->executeFromState($state);

        $contact->refresh();
        $tagNames = $contact->tags->pluck('name')->all();
        $this->assertContains('vip_lead', $tagNames);
        $this->assertContains('hot', $tagNames);
        $this->assertSame('95', $contact->custom_fields['lead_score'] ?? null);

        $state->refresh();
        $this->assertSame(ChatbotFlowStateStatus::Completed, $state->status);
    }

    public function test_engine_evaluates_condition_and_branches_yes_when_message_is_read(): void
    {
        $campaign = DripCampaign::factory()->create([
            'status' => ChatbotFlowStatus::Active,
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'cond_node',
                        'type' => 'condition',
                        'data' => [
                            'condition_type' => 'whatsapp_read',
                            'target_template' => 'promo_msg',
                        ],
                    ],
                    [
                        'id' => 'yes_node',
                        'type' => 'contactOperation',
                        'data' => [
                            'operation_type' => 'tag',
                            'tags' => ['read_the_promo'],
                        ],
                    ],
                    [
                        'id' => 'no_node',
                        'type' => 'contactOperation',
                        'data' => [
                            'operation_type' => 'tag',
                            'tags' => ['did_not_read'],
                        ],
                    ],
                ],
                'edges' => [
                    [
                        'source' => 'cond_node',
                        'sourceHandle' => 'yes',
                        'target' => 'yes_node',
                    ],
                    [
                        'source' => 'cond_node',
                        'sourceHandle' => 'no',
                        'target' => 'no_node',
                    ],
                ],
            ],
        ]);

        $contact = Contact::factory()->create();
        $conversation = Conversation::factory()->create(['contact_id' => $contact->id]);

        // Create outbound message that is read
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::Outbound,
            'body' => 'promo_msg',
            'status' => MessageStatus::Read,
            'read_at' => now(),
        ]);

        $state = DripCampaignState::create([
            'drip_campaign_id' => $campaign->id,
            'conversation_id' => $conversation->id,
            'current_node_id' => 'cond_node',
            'status' => ChatbotFlowStateStatus::Active,
        ]);

        $engine = app(DripFlowEngine::class);
        $engine->executeFromState($state);

        $contact->refresh();
        $tagNames = $contact->tags->pluck('name')->all();
        $this->assertContains('read_the_promo', $tagNames);
        $this->assertNotContains('did_not_read', $tagNames);

        $state->refresh();
        $this->assertSame(ChatbotFlowStateStatus::Completed, $state->status);
    }

    public function test_engine_evaluates_condition_with_wait_and_schedules_delayed_job_when_unread(): void
    {
        Queue::fake();

        $campaign = DripCampaign::factory()->create([
            'status' => ChatbotFlowStatus::Active,
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'cond_node',
                        'type' => 'condition',
                        'data' => [
                            'condition_type' => 'whatsapp_read',
                            'condition_wait' => '1 day',
                            'wait_seconds' => 86400,
                        ],
                    ],
                    [
                        'id' => 'no_node',
                        'type' => 'contactOperation',
                        'data' => [
                            'operation_type' => 'tag',
                            'tags' => ['did_not_read_after_1_day'],
                        ],
                    ],
                ],
                'edges' => [
                    [
                        'source' => 'cond_node',
                        'sourceHandle' => 'no',
                        'target' => 'no_node',
                    ],
                ],
            ],
        ]);

        $contact = Contact::factory()->create();
        $conversation = Conversation::factory()->create(['contact_id' => $contact->id]);

        // Create outbound message that is delivered but NOT read
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::Outbound,
            'status' => MessageStatus::Delivered,
            'read_at' => null,
        ]);

        $state = DripCampaignState::create([
            'drip_campaign_id' => $campaign->id,
            'conversation_id' => $conversation->id,
            'current_node_id' => 'cond_node',
            'status' => ChatbotFlowStateStatus::Active,
        ]);

        $engine = app(DripFlowEngine::class);
        $engine->executeFromState($state);

        $state->refresh();
        $this->assertSame(ChatbotFlowStateStatus::Waiting, $state->status);
        $this->assertNotNull($state->expires_at);
        $this->assertTrue($state->variables['cond_waited_cond_node'] ?? false);

        Queue::assertPushed(ExecuteDripStepJob::class);
    }
}
