<?php

namespace Tests\Unit\Inbox;

use App\Domains\Inbox\Services\CamsOutboundPayloadBuilder;
use App\Domains\Inbox\Services\MessagingWindowService;
use App\Enums\MessageDirection;
use App\Enums\MessageType;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsappLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class MessagingWindowServiceTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private MessagingWindowService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->service = app(MessagingWindowService::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_conversation_is_within_window_after_recent_inbound(): void
    {
        $conversation = $this->createConversation();

        Message::query()->create([
            'conversation_id' => $conversation->id,
            'body' => 'Hello',
            'direction' => MessageDirection::Inbound,
            'message_type' => MessageType::Text,
            'status' => \App\Enums\MessageStatus::Delivered,
            'created_at' => now()->subHours(2),
        ]);

        $this->assertTrue($this->service->isWithinServiceWindow($conversation));
    }

    public function test_conversation_is_outside_window_when_inbound_is_stale(): void
    {
        $conversation = $this->createConversation();

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'body' => 'Hello',
            'direction' => MessageDirection::Inbound,
            'message_type' => MessageType::Text,
            'status' => \App\Enums\MessageStatus::Delivered,
        ]);
        $message->forceFill(['created_at' => now()->subHours(30)])->save();

        $this->assertFalse($this->service->isWithinServiceWindow($conversation));
    }

    public function test_text_payload_builder_formats_recipients(): void
    {
        $line = $this->testLine;
        $contact = Contact::factory()->create(['phone' => '918888888899']);
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $line->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'line_phone' => $line->phone,
            'contact_name' => $contact->name,
        ]);

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'body' => 'Hello there',
            'direction' => MessageDirection::Outbound,
            'message_type' => MessageType::Text,
            'status' => \App\Enums\MessageStatus::Queued,
        ]);

        $payload = app(CamsOutboundPayloadBuilder::class)->build($message, $conversation, $line);

        $this->assertSame('message', $payload['Type']);
        $this->assertSame('text', $payload['MessageType']);
        $this->assertMatchesRegularExpression('/^\d+$/', $payload['From']);
        $this->assertMatchesRegularExpression('/^\d+$/', $payload['To']);
        $this->assertStringContainsString('Hello there', $payload['Content']);
    }

    private function createConversation(): Conversation
    {
        $contact = Contact::factory()->create();

        return Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'line_phone' => $this->testLine->phone,
            'contact_name' => $contact->name,
        ]);
    }
}
