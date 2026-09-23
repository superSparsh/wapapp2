<?php

declare(strict_types=1);

namespace Tests\Feature\Campaigns;

use App\Domains\Campaigns\Services\CampaignInboundResponseService;
use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CampaignInboundResponseServiceTest extends TestCase
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

    public function test_inbound_reply_marks_recipient_as_response_and_increments_campaign_total(): void
    {
        tenancy()->initialize($this->testTenant);

        $campaign = Campaign::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'status' => CampaignStatus::Completed,
            'total_recipients' => 1,
            'total_response' => 0,
        ]);

        $contact = Contact::factory()->create(['phone' => '918888820001']);
        $recipient = CampaignRecipient::factory()->delivered()->create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'contact_phone' => '918888820001',
            'sent_at' => now()->subHour(),
            'delivered_at' => now()->subHour(),
        ]);

        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'line_phone' => $this->testLine->phone,
        ]);

        $inbound = Message::query()->create([
            'conversation_id' => $conversation->id,
            'body' => 'Interested!',
            'direction' => MessageDirection::Inbound,
            'message_type' => MessageType::Text,
            'status' => MessageStatus::Delivered,
            'external_message_id' => 'wamid.reply-1',
            'sent_at' => now(),
        ]);

        app(CampaignInboundResponseService::class)->recordReply($conversation, $inbound);

        $recipient->refresh();
        $campaign->refresh();

        $this->assertSame(CampaignRecipientStatus::Response, $recipient->status);
        $this->assertNotNull($recipient->responded_at);
        $this->assertSame(1, (int) $campaign->total_response);
    }

    public function test_second_reply_does_not_double_count_response(): void
    {
        tenancy()->initialize($this->testTenant);

        $campaign = Campaign::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'total_response' => 1,
        ]);

        $contact = Contact::factory()->create(['phone' => '918888820002']);
        CampaignRecipient::factory()->create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'contact_phone' => '918888820002',
            'status' => CampaignRecipientStatus::Response,
            'sent_at' => now()->subHour(),
            'responded_at' => now()->subMinutes(5),
        ]);

        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'line_phone' => $this->testLine->phone,
        ]);

        $inbound = Message::query()->create([
            'conversation_id' => $conversation->id,
            'body' => 'Again',
            'direction' => MessageDirection::Inbound,
            'message_type' => MessageType::Text,
            'status' => MessageStatus::Delivered,
            'external_message_id' => 'wamid.reply-2',
            'sent_at' => now(),
        ]);

        app(CampaignInboundResponseService::class)->recordReply($conversation, $inbound);

        $campaign->refresh();
        $this->assertSame(1, (int) $campaign->total_response);
    }
}
