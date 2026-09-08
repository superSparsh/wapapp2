<?php

namespace Tests\Feature\Inbox;

use App\Domains\Inbox\Contracts\OutboundMessageGateway;
use App\Domains\Inbox\Jobs\SendOutboundMessageJob;
use App\Domains\Inbox\Services\InboxMessageService;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class InboxOutboundTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private InboxMessageService $messageService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->messageService = app(InboxMessageService::class);
        Storage::fake('public');
        Queue::fake();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_text_send_is_blocked_outside_service_window(): void
    {
        $conversation = $this->createConversation();
        $this->recordStaleInbound($conversation);

        $this->actingAsTenantUser()
            ->postJson(route('inbox.api.send', $conversation), ['body' => 'Too late'])
            ->assertStatus(422);
    }

    public function test_text_send_works_within_service_window(): void
    {
        $conversation = $this->createConversation();
        $this->messageService->recordInbound($conversation, 'Recent hello');

        $this->actingAsTenantUser()
            ->postJson(route('inbox.api.send', $conversation), ['body' => 'Reply now'])
            ->assertCreated()
            ->assertJsonPath('message.body', 'Reply now');

        Queue::assertPushed(SendOutboundMessageJob::class);
    }

    public function test_template_send_works_outside_service_window(): void
    {
        $conversation = $this->createConversation();
        $this->recordStaleInbound($conversation);

        $this->actingAsTenantUser()
            ->postJson(route('inbox.api.send-template', $conversation), [
                'template_code' => 'welcome_template',
            ])
            ->assertCreated()
            ->assertJsonPath('message.message_type', MessageType::Template->value);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'message_type' => MessageType::Template->value,
            'body' => 'welcome_template',
        ]);
    }

    public function test_media_send_queues_outbound_message(): void
    {
        $conversation = $this->createConversation();
        $this->messageService->recordInbound($conversation, 'Recent hello');

        $file = UploadedFile::fake()->image('photo.jpg');

        $this->actingAsTenantUser()
            ->post(route('inbox.api.send-media', $conversation), [
                'media_type' => 'image',
                'file' => $file,
                'caption' => 'Check this',
            ])
            ->assertCreated()
            ->assertJsonPath('message.message_type', MessageType::Image->value);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'message_type' => MessageType::Image->value,
            'body' => 'Check this',
        ]);
    }

    public function test_alibaba_gateway_marks_message_sent_on_success(): void
    {
        config([
            'whatsapp.alibaba.access_key_id' => 'test-key',
            'whatsapp.alibaba.access_key_secret' => 'test-secret',
            'whatsapp.alibaba.endpoint' => 'cams.test.local',
        ]);

        Http::fake([
            'https://cams.test.local/*' => Http::response(['MessageId' => 'wamid.TEST123'], 200),
        ]);

        $conversation = $this->createConversation();
        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'body' => 'CAMS hello',
            'direction' => \App\Enums\MessageDirection::Outbound,
            'message_type' => MessageType::Text,
            'status' => MessageStatus::Queued,
        ]);

        app(OutboundMessageGateway::class)->send($message->fresh());

        $message->refresh();
        $this->assertSame(MessageStatus::Sent, $message->status);
        $this->assertSame('wamid.TEST123', $message->external_message_id);
    }

    public function test_window_status_endpoint_reports_state(): void
    {
        $conversation = $this->createConversation();
        $this->messageService->recordInbound($conversation, 'Recent hello');

        $this->actingAsTenantUser()
            ->getJson(route('inbox.api.window', $conversation))
            ->assertOk()
            ->assertJsonPath('within_window', true);
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
            'last_message_at' => now(),
        ]);
    }

    private function recordStaleInbound(Conversation $conversation): void
    {
        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'body' => 'Old hello',
            'direction' => \App\Enums\MessageDirection::Inbound,
            'message_type' => MessageType::Text,
            'status' => MessageStatus::Delivered,
        ]);
        $message->forceFill(['created_at' => now()->subHours(30)])->save();
    }
}
