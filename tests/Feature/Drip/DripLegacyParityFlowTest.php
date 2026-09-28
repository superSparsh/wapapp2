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

    public function test_condition_wait_does_not_take_no_branch_before_timeout(): void
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
                            'condition_wait' => '15 minutes',
                            'wait_seconds' => 900,
                            'yes_target' => 'next',
                            'no_target' => 'no_node',
                        ],
                    ],
                    [
                        'id' => 'yes_node',
                        'type' => 'contactOperation',
                        'data' => [
                            'operation_type' => 'tag',
                            'tags' => ['read_early'],
                        ],
                    ],
                    [
                        'id' => 'no_node',
                        'type' => 'contactOperation',
                        'data' => [
                            'operation_type' => 'tag',
                            'tags' => ['timed_out'],
                        ],
                    ],
                ],
                'edges' => [
                    ['source' => 'cond_node', 'sourceHandle' => 'yes', 'target' => 'yes_node'],
                    ['source' => 'cond_node', 'sourceHandle' => 'no', 'target' => 'no_node'],
                ],
            ],
        ]);

        $contact = Contact::factory()->create();
        $conversation = Conversation::factory()->create(['contact_id' => $contact->id]);
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

        // Re-enter while still unread and before timeout — must keep waiting.
        $state->forceFill(['status' => ChatbotFlowStateStatus::Active])->save();
        $engine->executeFromState($state->fresh());
        $state->refresh();
        $contact->refresh();

        $this->assertSame(ChatbotFlowStateStatus::Waiting, $state->status);
        $this->assertSame('cond_node', $state->current_node_id);
        $this->assertNotContains('timed_out', $contact->tags->pluck('name')->all());
        $this->assertNotContains('read_early', $contact->tags->pluck('name')->all());
    }

    public function test_condition_resumes_yes_branch_when_message_is_read_during_wait(): void
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
                            'condition_wait' => '15 minutes',
                            'wait_seconds' => 900,
                            'yes_target' => 'next',
                            'no_target' => 'end',
                        ],
                    ],
                    [
                        'id' => 'wait_node',
                        'type' => 'delay',
                        'data' => [
                            'delay_value' => 1,
                            'delay_unit' => 'minutes',
                        ],
                    ],
                    [
                        'id' => 'after_wait',
                        'type' => 'contactOperation',
                        'data' => [
                            'operation_type' => 'tag',
                            'tags' => ['after_wait_step'],
                        ],
                    ],
                ],
                'edges' => [
                    ['source' => 'cond_node', 'sourceHandle' => 'yes', 'target' => 'wait_node'],
                    ['source' => 'wait_node', 'target' => 'after_wait'],
                ],
            ],
        ]);

        $contact = Contact::factory()->create();
        $conversation = Conversation::factory()->create(['contact_id' => $contact->id]);
        $message = Message::factory()->create([
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
        $this->assertSame('cond_node', $state->current_node_id);

        // User reads the message during the evaluation window.
        $message->forceFill([
            'status' => MessageStatus::Read,
            'read_at' => now(),
        ])->save();

        $resumed = app(\App\Domains\Drip\Services\DripConditionResumeService::class)
            ->resumeForConversation($conversation);
        $this->assertSame(1, $resumed);
        Queue::assertPushed(ExecuteDripStepJob::class);

        // Simulate the resume job running immediately.
        $state->forceFill(['status' => ChatbotFlowStateStatus::Active])->save();
        $engine->executeFromState($state->fresh());
        $state->refresh();

        // Yes → Wait delay scheduled; current node advanced to after_wait.
        $this->assertSame(ChatbotFlowStateStatus::Waiting, $state->status);
        $this->assertSame('after_wait', $state->current_node_id);

        $state->forceFill(['status' => ChatbotFlowStateStatus::Active])->save();
        $engine->executeFromState($state->fresh());

        $contact->refresh();
        $this->assertContains('after_wait_step', $contact->tags->pluck('name')->all());
        $this->assertSame(ChatbotFlowStateStatus::Completed, $state->fresh()->status);
    }

    public function test_engine_delivered_condition_waits_then_yes_on_delivery(): void
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
                            'condition_type' => 'whatsapp_delivered',
                            'wait_seconds' => 900,
                            'yes_target' => 'next',
                            'no_target' => 'end',
                        ],
                    ],
                    [
                        'id' => 'yes_node',
                        'type' => 'contactOperation',
                        'data' => [
                            'operation_type' => 'tag',
                            'tags' => ['was_delivered'],
                        ],
                    ],
                ],
                'edges' => [
                    ['source' => 'cond_node', 'sourceHandle' => 'yes', 'target' => 'yes_node'],
                ],
            ],
        ]);

        $contact = Contact::factory()->create();
        $conversation = Conversation::factory()->create(['contact_id' => $contact->id]);
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::Outbound,
            'status' => MessageStatus::Sent,
            'delivered_at' => null,
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

        $message->forceFill([
            'status' => MessageStatus::Delivered,
            'delivered_at' => now(),
        ])->save();

        $state->forceFill(['status' => ChatbotFlowStateStatus::Active])->save();
        $engine->executeFromState($state->fresh());

        $contact->refresh();
        $this->assertContains('was_delivered', $contact->tags->pluck('name')->all());
        $this->assertSame(ChatbotFlowStateStatus::Completed, $state->fresh()->status);
    }

    public function test_engine_failed_condition_takes_yes_when_message_fails(): void
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
                            'condition_type' => 'whatsapp_failed',
                            'wait_seconds' => 900,
                            'yes_target' => 'next',
                            'no_target' => 'end',
                        ],
                    ],
                    [
                        'id' => 'yes_node',
                        'type' => 'contactOperation',
                        'data' => [
                            'operation_type' => 'tag',
                            'tags' => ['send_failed'],
                        ],
                    ],
                ],
                'edges' => [
                    ['source' => 'cond_node', 'sourceHandle' => 'yes', 'target' => 'yes_node'],
                ],
            ],
        ]);

        $contact = Contact::factory()->create();
        $conversation = Conversation::factory()->create(['contact_id' => $contact->id]);
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::Outbound,
            'status' => MessageStatus::Sent,
            'failed_at' => null,
        ]);

        $state = DripCampaignState::create([
            'drip_campaign_id' => $campaign->id,
            'conversation_id' => $conversation->id,
            'current_node_id' => 'cond_node',
            'status' => ChatbotFlowStateStatus::Active,
        ]);

        $engine = app(DripFlowEngine::class);
        $engine->executeFromState($state);
        $this->assertSame(ChatbotFlowStateStatus::Waiting, $state->fresh()->status);

        $message->forceFill([
            'status' => MessageStatus::Failed,
            'failed_at' => now(),
        ])->save();

        $state->forceFill(['status' => ChatbotFlowStateStatus::Active])->save();
        $engine->executeFromState($state->fresh());

        $contact->refresh();
        $this->assertContains('send_failed', $contact->tags->pluck('name')->all());
    }

    public function test_engine_reply_condition_takes_yes_on_inbound(): void
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
                            'condition_type' => 'whatsapp_reply',
                            'wait_seconds' => 900,
                            'yes_target' => 'next',
                            'no_target' => 'end',
                        ],
                    ],
                    [
                        'id' => 'yes_node',
                        'type' => 'contactOperation',
                        'data' => [
                            'operation_type' => 'tag',
                            'tags' => ['replied'],
                        ],
                    ],
                ],
                'edges' => [
                    ['source' => 'cond_node', 'sourceHandle' => 'yes', 'target' => 'yes_node'],
                ],
            ],
        ]);

        $contact = Contact::factory()->create();
        $conversation = Conversation::factory()->create(['contact_id' => $contact->id]);
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::Outbound,
            'status' => MessageStatus::Delivered,
            'created_at' => now()->subMinute(),
        ]);

        $state = DripCampaignState::create([
            'drip_campaign_id' => $campaign->id,
            'conversation_id' => $conversation->id,
            'current_node_id' => 'cond_node',
            'status' => ChatbotFlowStateStatus::Active,
        ]);

        $engine = app(DripFlowEngine::class);
        $engine->executeFromState($state);
        $this->assertSame(ChatbotFlowStateStatus::Waiting, $state->fresh()->status);

        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::Inbound,
            'body' => 'interested',
        ]);
        $conversation->forceFill(['replied_at' => now()])->save();

        $state->forceFill(['status' => ChatbotFlowStateStatus::Active])->save();
        $engine->executeFromState($state->fresh());

        $contact->refresh();
        $this->assertContains('replied', $contact->tags->pluck('name')->all());
    }

    public function test_unread_condition_waits_and_takes_yes_only_after_timeout(): void
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
                            'condition_type' => 'whatsapp_unread',
                            'wait_seconds' => 900,
                            'yes_target' => 'next',
                            'no_target' => 'no_node',
                        ],
                    ],
                    [
                        'id' => 'yes_node',
                        'type' => 'contactOperation',
                        'data' => [
                            'operation_type' => 'tag',
                            'tags' => ['still_unread'],
                        ],
                    ],
                    [
                        'id' => 'no_node',
                        'type' => 'contactOperation',
                        'data' => [
                            'operation_type' => 'tag',
                            'tags' => ['was_read'],
                        ],
                    ],
                ],
                'edges' => [
                    ['source' => 'cond_node', 'sourceHandle' => 'yes', 'target' => 'yes_node'],
                    ['source' => 'cond_node', 'sourceHandle' => 'no', 'target' => 'no_node'],
                ],
            ],
        ]);

        $contact = Contact::factory()->create();
        $conversation = Conversation::factory()->create(['contact_id' => $contact->id]);
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
        $this->assertNotContains('still_unread', $contact->fresh()->tags->pluck('name')->all());

        // Timeout reached while still unread → Yes.
        $state->forceFill([
            'status' => ChatbotFlowStateStatus::Active,
            'expires_at' => now()->subSecond(),
        ])->save();
        $engine->executeFromState($state->fresh());

        $contact->refresh();
        $this->assertContains('still_unread', $contact->tags->pluck('name')->all());
        $this->assertNotContains('was_read', $contact->tags->pluck('name')->all());
    }

    public function test_unread_condition_takes_no_when_read_during_wait(): void
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
                            'condition_type' => 'whatsapp_unread',
                            'wait_seconds' => 900,
                            'yes_target' => 'next',
                            'no_target' => 'no_node',
                        ],
                    ],
                    [
                        'id' => 'no_node',
                        'type' => 'contactOperation',
                        'data' => [
                            'operation_type' => 'tag',
                            'tags' => ['was_read'],
                        ],
                    ],
                ],
                'edges' => [
                    ['source' => 'cond_node', 'sourceHandle' => 'no', 'target' => 'no_node'],
                ],
            ],
        ]);

        $contact = Contact::factory()->create();
        $conversation = Conversation::factory()->create(['contact_id' => $contact->id]);
        $message = Message::factory()->create([
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
        $this->assertSame(ChatbotFlowStateStatus::Waiting, $state->fresh()->status);

        $message->forceFill([
            'status' => MessageStatus::Read,
            'read_at' => now(),
        ])->save();

        $state->forceFill(['status' => ChatbotFlowStateStatus::Active])->save();
        $engine->executeFromState($state->fresh());

        $contact->refresh();
        $this->assertContains('was_read', $contact->tags->pluck('name')->all());
    }

    public function test_custom_variable_condition_does_not_wait(): void
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
                            'condition_type' => 'custom_variable',
                            'condition_variable' => 'city',
                            'condition_operator' => 'equals',
                            'condition_value' => 'Delhi',
                            'condition_wait' => '1 day',
                            'wait_seconds' => 86400,
                            'yes_target' => 'next',
                            'no_target' => 'no_node',
                        ],
                    ],
                    [
                        'id' => 'yes_node',
                        'type' => 'contactOperation',
                        'data' => [
                            'operation_type' => 'tag',
                            'tags' => ['delhi_lead'],
                        ],
                    ],
                    [
                        'id' => 'no_node',
                        'type' => 'contactOperation',
                        'data' => [
                            'operation_type' => 'tag',
                            'tags' => ['other_city'],
                        ],
                    ],
                ],
                'edges' => [
                    ['source' => 'cond_node', 'sourceHandle' => 'yes', 'target' => 'yes_node'],
                    ['source' => 'cond_node', 'sourceHandle' => 'no', 'target' => 'no_node'],
                ],
            ],
        ]);

        $contact = Contact::factory()->create([
            'custom_fields' => ['city' => 'Delhi'],
        ]);
        $conversation = Conversation::factory()->create(['contact_id' => $contact->id]);

        $state = DripCampaignState::create([
            'drip_campaign_id' => $campaign->id,
            'conversation_id' => $conversation->id,
            'current_node_id' => 'cond_node',
            'status' => ChatbotFlowStateStatus::Active,
        ]);

        app(DripFlowEngine::class)->executeFromState($state);

        $contact->refresh();
        $this->assertContains('delhi_lead', $contact->tags->pluck('name')->all());
        $this->assertSame(ChatbotFlowStateStatus::Completed, $state->fresh()->status);
        Queue::assertNotPushed(ExecuteDripStepJob::class);
    }

    public function test_condition_resume_skips_delay_waits(): void
    {
        Queue::fake();

        $contact = Contact::factory()->create();
        $conversation = Conversation::factory()->create(['contact_id' => $contact->id]);

        $delayState = DripCampaignState::create([
            'drip_campaign_id' => DripCampaign::factory()->create()->id,
            'conversation_id' => $conversation->id,
            'current_node_id' => 'after_delay',
            'status' => ChatbotFlowStateStatus::Waiting,
            'variables' => [],
            'expires_at' => now()->addMinutes(5),
        ]);

        $condState = DripCampaignState::create([
            'drip_campaign_id' => DripCampaign::factory()->create()->id,
            'conversation_id' => $conversation->id,
            'current_node_id' => 'cond_node',
            'status' => ChatbotFlowStateStatus::Waiting,
            'variables' => ['cond_waited_cond_node' => true],
            'expires_at' => now()->addDay(),
        ]);

        $resumed = app(\App\Domains\Drip\Services\DripConditionResumeService::class)
            ->resumeForConversation($conversation);

        $this->assertSame(1, $resumed);
        Queue::assertPushed(ExecuteDripStepJob::class, 1);
        Queue::assertPushed(ExecuteDripStepJob::class, fn ($job) => $job->dripCampaignStateId === $condState->id);
        Queue::assertNotPushed(ExecuteDripStepJob::class, fn ($job) => $job->dripCampaignStateId === $delayState->id);
    }
}
