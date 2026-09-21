<?php

declare(strict_types=1);

namespace Tests\Feature\Drip;

use App\Domains\Audience\Enums\ContactStatus;
use App\Domains\Audience\Services\ContactService;
use App\Domains\Drip\Jobs\ExecuteDripStepJob;
use App\Domains\Drip\Services\DripFlowEngine;
use App\Domains\Drip\Services\DripTriggerDispatcher;
use App\Enums\ChatbotFlowStateStatus;
use App\Enums\ChatbotFlowStatus;
use App\Enums\ContactOptInStatus;
use App\Enums\TenantUserAccountType;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\DripCampaign;
use App\Models\DripCampaignState;
use App\Models\TenantUserAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class DripTriggerParityTest extends TestCase
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

    public function test_unsubscribe_dispatches_goodbye_drip(): void
    {
        Queue::fake();

        $campaign = DripCampaign::factory()->active()->withNodes(1)->create([
            'trigger_type' => 'say-goodbye-subscriber',
        ]);

        $contact = Contact::factory()->create([
            'status' => ContactStatus::Subscribed,
            'opt_in_status' => ContactOptInStatus::OptedIn,
        ]);

        app(ContactService::class)->unsubscribe($contact);

        $this->assertDatabaseHas('drip_campaign_states', [
            'drip_campaign_id' => $campaign->id,
        ]);
        Queue::assertPushed(ExecuteDripStepJob::class);
    }

    public function test_tag_added_enrolls_matching_campaign_only(): void
    {
        Queue::fake();

        $match = DripCampaign::factory()->active()->withNodes(1)->create([
            'trigger_type' => 'tag-added',
            'trigger_options' => ['tag_name' => 'vip'],
        ]);
        DripCampaign::factory()->active()->withNodes(1)->create([
            'trigger_type' => 'tag-added',
            'trigger_options' => ['tag_name' => 'other'],
        ]);

        $contact = Contact::factory()->create();
        $contact->syncTags(['vip']);

        $this->assertSame(1, DripCampaignState::query()->where('drip_campaign_id', $match->id)->count());
        $this->assertSame(1, DripCampaignState::query()->count());
    }

    public function test_condition_no_branch_uses_no_target_when_edges_missing(): void
    {
        $campaign = DripCampaign::factory()->create([
            'status' => ChatbotFlowStatus::Active,
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'cond_node',
                        'type' => 'enhancedCondition',
                        'data' => [
                            'condition_type' => 'custom_variable',
                            'condition_variable' => 'lead_score',
                            'condition_operator' => 'equals',
                            'condition_value' => '100',
                            'no_target' => 'no_node',
                        ],
                    ],
                    [
                        'id' => 'yes_node',
                        'type' => 'contactOperation',
                        'data' => [
                            'operation_type' => 'tag',
                            'tags' => ['yes_path'],
                        ],
                    ],
                    [
                        'id' => 'no_node',
                        'type' => 'contactOperation',
                        'data' => [
                            'operation_type' => 'tag',
                            'tags' => ['no_path'],
                        ],
                    ],
                ],
                'edges' => [],
            ],
        ]);

        $contact = Contact::factory()->create([
            'custom_fields' => ['lead_score' => '50'],
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
        $this->assertContains('no_path', $contact->tags->pluck('name')->all());
        $this->assertNotContains('yes_path', $contact->tags->pluck('name')->all());
    }

    public function test_api_trigger_enrolls_contact_by_phone(): void
    {
        Queue::fake();

        $token = Str::random(60);
        $this->testUser->forceFill(['api_token' => $token])->save();

        tenancy()->central(function () use ($token): void {
            TenantUserAccess::query()->create([
                'email' => strtolower((string) $this->testUser->email),
                'phone' => $this->testUser->phone,
                'tenant_id' => $this->testTenant->id,
                'account_type' => TenantUserAccountType::Owner,
                'is_active' => true,
                'api_token' => $token,
            ]);
        });

        $campaign = DripCampaign::factory()->active()->withNodes(1)->create([
            'trigger_type' => 'api',
        ]);
        $contact = Contact::factory()->create(['phone' => '919876543210']);

        $this->postJson(route('api.drip.trigger', ['campaign' => $campaign->uuid]), [
            'api_token' => $token,
            'phone' => '9876543210',
        ])->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('drip_campaign_states', [
            'drip_campaign_id' => $campaign->id,
        ]);
        Queue::assertPushed(ExecuteDripStepJob::class);
    }

    public function test_enroll_dedupes_same_enrollment_key(): void
    {
        Queue::fake();

        $campaign = DripCampaign::factory()->active()->withNodes(1)->create();
        $contact = Contact::factory()->create();
        $dispatcher = app(DripTriggerDispatcher::class);

        $this->assertTrue($dispatcher->enroll($campaign, $contact, enrollmentKey: 'week-2026-09-21'));
        $this->assertFalse($dispatcher->enroll($campaign, $contact, enrollmentKey: 'week-2026-09-21'));
        $this->assertSame(1, DripCampaignState::query()->where('drip_campaign_id', $campaign->id)->count());
    }
}
