<?php

namespace Tests\Feature\Inbox;

use App\Domains\Inbox\Services\InboxMessageService;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WalletAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class InboxHttpTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private InboxMessageService $messageService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->messageService = app(InboxMessageService::class);

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

    public function test_inbox_index_requires_authentication(): void
    {
        $this->get(route('inbox.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_inbox_and_open_conversation(): void
    {
        $contact = Contact::factory()->create(['name' => 'Inbox User', 'phone' => '918888888899']);
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'line_phone' => $this->testLine->phone,
            'contact_name' => $contact->name,
            'last_message_at' => now(),
        ]);

        Message::query()->create([
            'conversation_id' => $conversation->id,
            'body' => 'Inbound hello',
            'direction' => MessageDirection::Inbound,
            'message_type' => MessageType::Text,
            'status' => MessageStatus::Delivered,
            'created_at' => now()->subHour(),
        ]);

        $this->actingAsTenantUser()
            ->get(route('inbox.show', $conversation))
            ->assertOk()
            ->assertSee('Export by date')
            ->assertSee('Last 3 Months')
            ->assertSee('Last 1 Year')
            ->assertSee('Send opt-in')
            ->assertSee('Add New Contact')
            ->assertSee('India (+91)')
            ->assertSee('Afghanistan (+93)')
            ->assertSee('Inbox User')
            ->assertSee('Inbound hello')
            ->assertViewHas('availableLines', function (array $lines): bool {
                $line = collect($lines)->firstWhere('uuid', $this->testLine->uuid);

                return is_array($line)
                    && $line['label'] === 'Test Line (+91 99999 99999)';
            })
            ->assertViewHas('walletBalance')
            ->assertViewHas('walletBlocked', false)
            ->assertViewHas('threadsCursor')
            ->assertViewHas('threadsHasMore')
            ->assertViewHas('messagesHasMore')
            ->assertViewHas('messagesOldestId')
            ->assertViewHas('activeLine', fn ($line) => $line->id === $this->testLine->id);
    }

    public function test_user_can_send_message_via_api(): void
    {
        $contact = Contact::factory()->create();
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'line_phone' => $this->testLine->phone,
            'contact_name' => $contact->name,
            'last_message_at' => now(),
        ]);

        $this->messageService->recordInbound($conversation, 'Inbound hello');

        $this->actingAsTenantUser()
            ->postJson(route('inbox.api.send', $conversation), ['body' => 'Reply from agent'])
            ->assertCreated()
            ->assertJsonPath('message.body', 'Reply from agent');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'body' => 'Reply from agent',
            'direction' => 'outbound',
        ]);
    }

    public function test_threads_api_returns_paginated_payload(): void
    {
        $contact = Contact::factory()->create(['name' => 'API Thread']);
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'line_phone' => $this->testLine->phone,
            'contact_name' => $contact->name,
            'last_message_at' => now(),
        ]);

        $this->messageService->recordInbound($conversation, 'API preview');

        $this->actingAsTenantUser()
            ->getJson(route('inbox.api.threads'))
            ->assertOk()
            ->assertJsonPath('items.0.name', 'API Thread')
            ->assertJsonPath('items.0.phone', $contact->phone)
            ->assertJsonPath('items.0.preview', 'API preview')
            ->assertJsonPath('unread_total', 1);
    }

    public function test_user_can_delete_a_conversation(): void
    {
        $contact = Contact::factory()->create(['name' => 'Delete Me', 'phone' => '918888888800']);
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'line_phone' => $this->testLine->phone,
            'contact_name' => $contact->name,
            'last_message_at' => now(),
        ]);

        $this->messageService->recordInbound($conversation, 'Please delete');

        $this->actingAsTenantUser()
            ->deleteJson(route('inbox.api.destroy', $conversation))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
        $this->assertDatabaseMissing('messages', ['conversation_id' => $conversation->id]);
        $this->assertDatabaseHas('contacts', ['id' => $contact->id]);
    }

    public function test_inbox_sidebar_shows_unread_badge(): void
    {
        $contact = Contact::factory()->create(['name' => 'Unread Nav', 'phone' => '918888888811']);
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'line_phone' => $this->testLine->phone,
            'contact_name' => $contact->name,
            'last_message_at' => now(),
        ]);

        $this->messageService->recordInbound($conversation, 'Hello badge');

        $html = $this->actingAsTenantUser()
            ->get(route('inbox.index'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/data-inbox-nav-badge[^>]*>\s*1\s*</', $html);
        $this->assertStringContainsString('Hello badge', $html);
    }

    public function test_inbox_sidebar_badge_shows_on_other_pages(): void
    {
        $contact = Contact::factory()->create(['name' => 'Unread Nav', 'phone' => '918888888812']);
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'line_phone' => $this->testLine->phone,
            'contact_name' => $contact->name,
            'last_message_at' => now(),
        ]);

        $this->messageService->recordInbound($conversation, 'Hello dashboard badge');

        $html = $this->actingAsTenantUser()
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/data-inbox-nav-badge[^>]*>\s*1\s*</', $html);
        $this->assertStringContainsString('data-inbox-unread-url', $html);
    }

    public function test_unread_count_api_returns_total(): void
    {
        $contact = Contact::factory()->create(['name' => 'Unread API', 'phone' => '918888888813']);
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'line_phone' => $this->testLine->phone,
            'contact_name' => $contact->name,
            'last_message_at' => now(),
        ]);

        $this->messageService->recordInbound($conversation, 'Count me');

        $this->actingAsTenantUser()
            ->getJson(route('inbox.api.unread-count'))
            ->assertOk()
            ->assertJsonPath('unread_total', 1)
            ->assertJsonPath('latest.preview', 'Count me')
            ->assertJsonPath('latest.name', 'Unread API');
    }

    public function test_unread_total_counts_chats_not_messages(): void
    {
        $contactA = Contact::factory()->create(['name' => 'Chat A', 'phone' => '918888888821']);
        $contactB = Contact::factory()->create(['name' => 'Chat B', 'phone' => '918888888822']);

        $conversationA = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contactA->id,
            'contact_phone' => $contactA->phone,
            'line_phone' => $this->testLine->phone,
            'contact_name' => $contactA->name,
            'unread_count' => 3,
            'last_message_at' => now(),
        ]);
        $conversationB = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contactB->id,
            'contact_phone' => $contactB->phone,
            'line_phone' => $this->testLine->phone,
            'contact_name' => $contactB->name,
            'unread_count' => 1,
            'last_message_at' => now()->subSecond(),
        ]);

        $this->messageService->recordInbound($conversationA, 'A latest');
        $this->messageService->recordInbound($conversationB, 'B1');

        // Nav badge = chats with unread, not sum of message unread_counts (3+1+…).
        $this->assertGreaterThan(0, $conversationA->fresh()->unread_count);
        $this->assertGreaterThan(0, $conversationB->fresh()->unread_count);

        $this->actingAsTenantUser()
            ->getJson(route('inbox.api.unread-count'))
            ->assertOk()
            ->assertJsonPath('unread_total', 2);

        $this->actingAsTenantUser()
            ->getJson(route('inbox.api.threads'))
            ->assertOk()
            ->assertJsonPath('unread_total', 2);

        $this->actingAsTenantUser()
            ->postJson(route('inbox.api.read', $conversationA))
            ->assertOk();

        $this->assertSame(0, $conversationA->fresh()->unread_count);

        $this->actingAsTenantUser()
            ->getJson(route('inbox.api.unread-count'))
            ->assertOk()
            ->assertJsonPath('unread_total', 1);
    }

    public function test_opening_conversation_clears_thread_unread_badge(): void
    {
        $contact = Contact::factory()->create(['name' => 'Open Clear', 'phone' => '918888888823']);
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'line_phone' => $this->testLine->phone,
            'contact_name' => $contact->name,
            'last_message_at' => now(),
        ]);

        $this->messageService->recordInbound($conversation, 'See me');
        $this->messageService->recordInbound($conversation->fresh(), 'And me');

        $html = $this->actingAsTenantUser()
            ->get(route('inbox.show', $conversation))
            ->assertOk()
            ->getContent();

        $this->assertSame(0, $conversation->fresh()->unread_count);
        $this->assertMatchesRegularExpression(
            '/data-thread-uuid="'.$conversation->uuid.'"[^>]*>[\s\S]*?data-thread-unread[^>]*><\/span>/',
            $html,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/data-thread-uuid="'.$conversation->uuid.'"[^>]*>[\s\S]*?data-thread-unread[^>]*>\s*[1-9]/',
            $html,
        );
    }
}
