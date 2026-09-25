<?php

declare(strict_types=1);

namespace Tests\Feature\Audience;

use App\Domains\Audience\Enums\ContactStatus;
use App\Domains\Audience\Services\StopKeywordService;
use App\Domains\Inbox\Services\InboxOutboundService;
use App\Enums\CampaignRecipientStatus;
use App\Enums\ContactOptInStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageType;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class StopKeywordServiceTest extends TestCase
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

    public function test_stop_is_case_insensitive_and_unsubscribes_contact(): void
    {
        $outbound = Mockery::mock(InboxOutboundService::class);
        $outbound->shouldReceive('sendText')
            ->once()
            ->withArgs(function (Conversation $conversation, string $body, bool $enforceWindow, bool $allowStopped = false): bool {
                return str_contains($body, 'unsubscribed')
                    && str_contains($body, 'START')
                    && $enforceWindow === false
                    && $allowStopped === true;
            })
            ->andReturn(new Message);

        $this->app->instance(InboxOutboundService::class, $outbound);

        $contact = Contact::factory()->create([
            'phone' => '919876543210',
            'status' => ContactStatus::Subscribed,
            'opt_in_status' => ContactOptInStatus::OptedIn,
        ]);

        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
        ]);

        $inbound = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::Inbound,
            'message_type' => MessageType::Text,
            'body' => 'Stop',
        ]);

        $handled = app(StopKeywordService::class)->handle($conversation, $inbound);

        $this->assertTrue($handled);
        $contact->refresh();
        $this->assertSame(ContactStatus::Unsubscribed, $contact->status);
        $this->assertSame(ContactOptInStatus::OptedOut, $contact->opt_in_status);
        $this->assertTrue((bool) data_get($contact->metadata, 'stopped_via_keyword'));
    }

    public function test_stop_promotions_keyword_works(): void
    {
        $outbound = Mockery::mock(InboxOutboundService::class);
        $outbound->shouldReceive('sendText')->once()->andReturn(new Message);
        $this->app->instance(InboxOutboundService::class, $outbound);

        $contact = Contact::factory()->create([
            'phone' => '919811122233',
            'status' => ContactStatus::Subscribed,
        ]);
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
        ]);
        $inbound = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::Inbound,
            'body' => 'STOP PROMOTIONS',
        ]);

        $this->assertTrue(app(StopKeywordService::class)->handle($conversation, $inbound));
        $this->assertSame(ContactStatus::Unsubscribed, $contact->fresh()->status);
    }

    public function test_start_re_subscribes_stopped_contact(): void
    {
        $outbound = Mockery::mock(InboxOutboundService::class);
        $outbound->shouldReceive('sendText')
            ->once()
            ->withArgs(fn ($c, string $body) => str_contains($body, 'Welcome back'))
            ->andReturn(new Message);
        $this->app->instance(InboxOutboundService::class, $outbound);

        $contact = Contact::factory()->create([
            'phone' => '919900011122',
            'status' => ContactStatus::Unsubscribed,
            'opt_in_status' => ContactOptInStatus::OptedOut,
            'metadata' => ['stopped_via_keyword' => true],
        ]);
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
        ]);
        $inbound = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::Inbound,
            'body' => 'start',
        ]);

        $this->assertTrue(app(StopKeywordService::class)->handle($conversation, $inbound));
        $contact->refresh();
        $this->assertSame(ContactStatus::Subscribed, $contact->status);
        $this->assertSame(ContactOptInStatus::OptedIn, $contact->opt_in_status);
        $this->assertFalse((bool) data_get($contact->metadata, 'stopped_via_keyword'));
    }

    public function test_stop_marks_pending_campaign_recipients_unsubscribed(): void
    {
        $outbound = Mockery::mock(InboxOutboundService::class);
        $outbound->shouldReceive('sendText')->once()->andReturn(new Message);
        $this->app->instance(InboxOutboundService::class, $outbound);

        $contact = Contact::factory()->create([
            'phone' => '919955566677',
            'status' => ContactStatus::Subscribed,
        ]);
        $campaign = Campaign::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
        ]);
        $recipient = CampaignRecipient::factory()->create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'status' => CampaignRecipientStatus::Pending,
        ]);

        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
        ]);
        $inbound = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::Inbound,
            'body' => 'stop',
        ]);

        app(StopKeywordService::class)->handle($conversation, $inbound);

        $recipient->refresh();
        $this->assertSame(CampaignRecipientStatus::Unsubscribed, $recipient->status);
        $this->assertNotNull($recipient->unsubscribed_at);
    }
}
