<?php

namespace Tests\Feature\Inbox;

use App\Domains\Inbox\Contracts\OutboundMessageGateway;
use App\Domains\Inbox\Jobs\SendOutboundMessageJob;
use App\Domains\Inbox\Services\InboxMessageService;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Enums\RecordStatus;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Template;
use App\Models\WalletAccount;
use App\Models\WhatsappLine;
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

        WalletAccount::query()->create([
            'balance' => 500,
            'currency' => 'INR',
        ]);
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

    public function test_text_send_is_blocked_when_wallet_balance_is_low(): void
    {
        WalletAccount::query()->update(['balance' => 10]);

        $conversation = $this->createConversation();
        $this->messageService->recordInbound($conversation, 'Recent hello');

        $this->actingAsTenantUser()
            ->postJson(route('inbox.api.send', $conversation), ['body' => 'Should fail'])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Insufficient wallet balance (₹10.00). Please recharge to at least ₹50.00 to send messages.']);

        Queue::assertNothingPushed();
    }

    public function test_template_send_works_when_wallet_balance_is_low(): void
    {
        WalletAccount::query()->update(['balance' => 10]);

        $conversation = $this->createConversation();

        $this->actingAsTenantUser()
            ->postJson(route('inbox.api.send-template', $conversation), [
                'template_code' => '935757998997286999',
            ])
            ->assertCreated()
            ->assertJsonPath('message.message_type', MessageType::Template->value);
    }

    public function test_template_send_works_outside_service_window(): void
    {
        $conversation = $this->createConversation();
        $this->recordStaleInbound($conversation);

        $this->actingAsTenantUser()
            ->postJson(route('inbox.api.send-template', $conversation), [
                'template_code' => '935757998997286998',
            ])
            ->assertCreated()
            ->assertJsonPath('message.message_type', MessageType::Template->value);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'message_type' => MessageType::Template->value,
            'body' => '935757998997286998',
        ]);
    }

    public function test_template_send_rejects_local_non_provider_code(): void
    {
        $conversation = $this->createConversation();

        $this->actingAsTenantUser()
            ->postJson(route('inbox.api.send-template', $conversation), [
                'template_code' => 'welcome_template',
            ])
            ->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'This template is not approved on WhatsApp yet. Refresh templates and select an approved TemplateCode.',
            ]);
    }

    public function test_template_send_stores_preview_body_instead_of_provider_code(): void
    {
        Template::factory()->create([
            'name' => 'Welcome Offer',
            'code' => '935757998997286912',
            'whatsapp_line_id' => $this->testLine->id,
            'payload' => array_merge(Template::defaultPayload(), [
                'body' => ['text' => 'Hello $(name), welcome back.'],
                'buttons' => [['text' => 'Shop Now', 'type' => 'url']],
            ]),
        ]);

        $conversation = $this->createConversation();

        $this->actingAsTenantUser()
            ->postJson(route('inbox.api.send-template', $conversation), [
                'template_code' => '935757998997286912',
                'template_params' => ['name' => 'Sparsh'],
            ])
            ->assertCreated()
            ->assertJsonPath('message.body', 'Hello Sparsh, welcome back.')
            ->assertJsonPath('message.template_name', 'Welcome Offer')
            ->assertJsonPath('message.template_buttons.0.text', 'Shop Now');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'message_type' => MessageType::Template->value,
            'body' => 'Hello Sparsh, welcome back.',
        ]);
    }

    public function test_templates_api_lists_only_sendable_provider_templates(): void
    {
        Template::factory()->create([
            'name' => 'Local Only',
            'code' => 'inbox_welcome',
            'whatsapp_line_id' => null,
            'payload' => array_merge(Template::defaultPayload(), [
                'body' => ['text' => 'Hi $(name)'],
            ]),
        ]);

        Template::factory()->create([
            'name' => 'CAMS Ready',
            'code' => '935757998997286955',
            'whatsapp_line_id' => $this->testLine->id,
            'payload' => array_merge(Template::defaultPayload(), [
                'body' => ['text' => 'Hello from CAMS'],
                'buttons' => [['text' => 'Open', 'type' => 'url']],
            ]),
        ]);

        Template::factory()->draft()->create(['name' => 'Not Listed']);

        $this->createConversation();

        $this->actingAsTenantUser()
            ->getJson(route('inbox.api.templates'))
            ->assertOk()
            ->assertJsonPath('items.0.code', '935757998997286955')
            ->assertJsonPath('items.0.preview.body', 'Hello from CAMS')
            ->assertJsonPath('items.0.preview.buttons.0.text', 'Open')
            ->assertJsonMissing(['code' => 'inbox_welcome']);
    }

    public function test_templates_api_includes_unassigned_and_falls_back_to_other_lines(): void
    {
        Template::factory()->create([
            'name' => 'Other Line Template',
            'code' => '935757998997286966',
            'whatsapp_line_id' => WhatsappLine::query()->create([
                'phone' => '918888800099',
                'display_name' => 'Second Line',
                'status' => RecordStatus::Active,
                'is_default' => false,
            ])->id,
            'payload' => array_merge(Template::defaultPayload(), [
                'body' => ['text' => 'From another line'],
            ]),
        ]);

        $this->createConversation();

        $this->actingAsTenantUser()
            ->getJson(route('inbox.api.templates'))
            ->assertOk()
            ->assertJsonPath('items.0.code', '935757998997286966')
            ->assertJsonPath('items.0.preview.body', 'From another line');
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

    public function test_media_send_rejects_oversized_image(): void
    {
        $conversation = $this->createConversation();
        $this->messageService->recordInbound($conversation, 'Recent hello');

        $file = UploadedFile::fake()->create('huge.jpg', 6 * 1024, 'image/jpeg'); // 6 MB > 5 MB

        $this->actingAsTenantUser()
            ->postJson(route('inbox.api.send-media', $conversation), [
                'media_type' => 'image',
                'file' => $file,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_media_send_rejects_wrong_extension_for_type(): void
    {
        $conversation = $this->createConversation();
        $this->messageService->recordInbound($conversation, 'Recent hello');

        $file = UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf');

        $this->actingAsTenantUser()
            ->postJson(route('inbox.api.send-media', $conversation), [
                'media_type' => 'image',
                'file' => $file,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
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
            'direction' => MessageDirection::Outbound,
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

    public function test_interactive_composer_sends_button_message(): void
    {
        $conversation = $this->createConversation();
        $this->openServiceWindow($conversation);

        $this->actingAsTenantUser()
            ->postJson(route('inbox.api.send-interactive-compose', $conversation), [
                'type' => 'button',
                'body' => 'Need help?',
                'footer' => 'Tap one',
                'buttons' => [
                    ['title' => 'Yes'],
                    ['title' => 'No'],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('message.message_type', MessageType::Interactive->value)
            ->assertJsonPath('message.body', 'Need help?');

        Queue::assertPushed(SendOutboundMessageJob::class);
    }

    public function test_interactive_composer_sends_website_button(): void
    {
        $conversation = $this->createConversation();
        $this->openServiceWindow($conversation);

        $this->actingAsTenantUser()
            ->postJson(route('inbox.api.send-interactive-compose', $conversation), [
                'type' => 'cta_url',
                'body' => 'See our website',
                'button_text' => 'Open',
                'url' => 'https://wapapp.test',
            ])
            ->assertCreated()
            ->assertJsonPath('message.message_type', MessageType::Interactive->value);
    }

    public function test_export_all_supports_date_range_and_skip_phones(): void
    {
        $keep = $this->createConversation();
        $skip = $this->createConversation();
        $this->openServiceWindow($keep, 'Keep this chat');
        $this->openServiceWindow($skip, 'Skip this chat');

        $response = $this->actingAsTenantUser()
            ->get(route('inbox.api.export-all', [
                'from' => now()->toDateString(),
                'to' => now()->toDateString(),
                'skip_phones' => $skip->contact_phone,
            ]));

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Keep this chat', $csv);
        $this->assertStringNotContainsString('Skip this chat', $csv);
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
            'direction' => MessageDirection::Inbound,
            'message_type' => MessageType::Text,
            'status' => MessageStatus::Delivered,
        ]);
        $message->forceFill(['created_at' => now()->subHours(30)])->save();
    }

    private function openServiceWindow(Conversation $conversation, string $body = 'Recent hello'): void
    {
        Message::query()->create([
            'conversation_id' => $conversation->id,
            'body' => $body,
            'direction' => MessageDirection::Inbound,
            'message_type' => MessageType::Text,
            'status' => MessageStatus::Delivered,
            'created_at' => now()->subHour(),
        ]);
    }
}
