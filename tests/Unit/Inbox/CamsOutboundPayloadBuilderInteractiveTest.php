<?php

namespace Tests\Unit\Inbox;

use App\Domains\Inbox\Services\CamsOutboundPayloadBuilder;
use App\Enums\MessageDirection;
use App\Enums\MessageType;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CamsOutboundPayloadBuilderInteractiveTest extends TestCase
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

    public function test_builds_interactive_payload_for_flow_message(): void
    {
        $contact = Contact::factory()->create(['phone' => '918888888899']);
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'line_phone' => $this->testLine->phone,
            'contact_name' => $contact->name,
        ]);

        $interactive = [
            'type' => 'flow',
            'body' => ['text' => 'Open the flow'],
            'action' => [
                'name' => 'flow',
                'parameters' => [
                    'flow_id' => 'flow_meta_1',
                    'flow_cta' => 'Open',
                ],
            ],
        ];

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'body' => 'Open the flow',
            'direction' => MessageDirection::Outbound,
            'message_type' => MessageType::Interactive,
            'status' => \App\Enums\MessageStatus::Queued,
            'metadata' => [
                'interactive' => $interactive,
            ],
        ]);

        $payload = app(CamsOutboundPayloadBuilder::class)->build($message, $conversation, $this->testLine);

        $this->assertSame('message', $payload['Type']);
        $this->assertSame('interactive', $payload['MessageType']);

        $decoded = json_decode($payload['Content'], true);
        $this->assertSame('flow', $decoded['type']);
        $this->assertSame('flow_meta_1', $decoded['action']['parameters']['flow_id']);
    }
}
