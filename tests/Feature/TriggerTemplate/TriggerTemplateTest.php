<?php

namespace Tests\Feature\TriggerTemplate;

use App\Domains\Inbox\Jobs\SendOutboundMessageJob;
use App\Domains\TriggerTemplate\Enums\TriggerFireResult;
use App\Domains\TriggerTemplate\Services\TriggerTemplateEngine;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\TeamMember;
use App\Models\TriggerVariable;
use App\Models\WalletAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TriggerTemplateTest extends TestCase
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

    public function test_owner_can_view_trigger_template_index(): void
    {
        TriggerVariable::factory()->create([
            'variable_name' => 'pricing',
            'template_name' => 'Pricing Template',
        ]);

        $this->actingAsTenantUser()
            ->get(route('trigger-template.index'))
            ->assertOk()
            ->assertSee('Intent Response Setting')
            ->assertSee('pricing')
            ->assertSee('Pricing Template');
    }

    public function test_owner_can_create_trigger(): void
    {
        $this->actingAsTenantUser()
            ->post(route('trigger-template.store'), [
                'variable_name' => 'pricing',
                'template_code' => 'pricing_auto',
                'template_name' => 'Pricing Auto Response',
            ])
            ->assertRedirect(route('trigger-template.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('trigger_variables', [
            'variable_name' => 'pricing',
            'template_code' => 'pricing_auto',
            'template_name' => 'Pricing Auto Response',
        ]);
    }

    public function test_duplicate_trigger_name_is_rejected(): void
    {
        TriggerVariable::factory()->create(['variable_name' => 'pricing']);

        $this->actingAsTenantUser()
            ->from(route('trigger-template.index', ['modal' => 'add-trigger']))
            ->post(route('trigger-template.store'), [
                'variable_name' => 'pricing',
                'template_code' => 'other_template',
                'template_name' => 'Other Template',
            ])
            ->assertRedirect(route('trigger-template.index', ['modal' => 'add-trigger']))
            ->assertSessionHasErrors('variable_name');
    }

    public function test_owner_can_delete_trigger(): void
    {
        $trigger = TriggerVariable::factory()->create(['variable_name' => 'remove_me']);

        $this->actingAsTenantUser()
            ->delete(route('trigger-template.destroy', $trigger))
            ->assertRedirect(route('trigger-template.index'))
            ->assertSessionHas('status');

        $this->assertSoftDeleted('trigger_variables', ['id' => $trigger->id]);
    }

    public function test_team_member_cannot_access_trigger_template(): void
    {
        $member = TeamMember::factory()->create([
            'parent_user_id' => $this->testUser->id,
        ]);

        $this->actingAsTeamMember($member)
            ->get(route('trigger-template.index'))
            ->assertForbidden();
    }

    public function test_engine_fires_template_when_keyword_matches(): void
    {
        Queue::fake();

        WalletAccount::query()->create([
            'balance' => 500,
            'currency' => 'INR',
        ]);

        TriggerVariable::factory()->create([
            'variable_name' => 'hello',
            'template_code' => 'welcome_template',
            'template_name' => 'Welcome',
        ]);

        $conversation = $this->conversation();

        $engine = app(TriggerTemplateEngine::class);
        $message = app(\App\Domains\Inbox\Services\InboxMessageService::class)
            ->recordInbound($conversation, 'Say hello to me');

        $result = $engine->process($conversation->refresh(), $message);

        $this->assertSame(TriggerFireResult::Fired, $result);
        Queue::assertPushed(SendOutboundMessageJob::class);
        $this->assertSame(0, $conversation->refresh()->unread_count);
    }

    public function test_engine_blocks_when_wallet_balance_is_low(): void
    {
        Queue::fake();

        WalletAccount::query()->create([
            'balance' => 25,
            'currency' => 'INR',
        ]);

        TriggerVariable::factory()->create([
            'variable_name' => 'hello',
            'template_code' => 'welcome_template',
        ]);

        $conversation = $this->conversation();
        $message = app(\App\Domains\Inbox\Services\InboxMessageService::class)
            ->recordInbound($conversation, 'hello there');

        $result = app(TriggerTemplateEngine::class)->process($conversation->refresh(), $message);

        $this->assertSame(TriggerFireResult::WalletBlocked, $result);
        Queue::assertNothingPushed();
    }

    public function test_engine_fires_any_message_trigger_on_first_inbound_only(): void
    {
        Queue::fake();

        WalletAccount::query()->create([
            'balance' => 500,
            'currency' => 'INR',
        ]);

        TriggerVariable::factory()->anyMessage()->create([
            'template_code' => 'welcome_template',
        ]);

        $conversation = $this->conversation();
        $messageService = app(\App\Domains\Inbox\Services\InboxMessageService::class);
        $engine = app(TriggerTemplateEngine::class);

        $first = $messageService->recordInbound($conversation, 'First contact message');
        $firstResult = $engine->process($conversation->refresh(), $first);
        $this->assertSame(TriggerFireResult::Fired, $firstResult);

        Queue::fake();

        $second = $messageService->recordInbound($conversation->refresh(), 'Second message');
        $secondResult = $engine->process($conversation->refresh(), $second);
        $this->assertSame(TriggerFireResult::NoMatch, $secondResult);
        Queue::assertNothingPushed();
    }

    private function conversation(): Conversation
    {
        $contact = Contact::factory()->create();

        return Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'line_phone' => $this->testLine->phone,
            'unread_count' => 1,
        ]);
    }
}
