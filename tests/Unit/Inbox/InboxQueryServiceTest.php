<?php

namespace Tests\Unit\Inbox;

use App\Domains\Inbox\Services\InboxMessageService;
use App\Domains\Inbox\Services\InboxQueryService;
use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class InboxQueryServiceTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private InboxQueryService $queryService;

    private InboxMessageService $messageService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->queryService = app(InboxQueryService::class);
        $this->messageService = app(InboxMessageService::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_it_lists_threads_for_default_line_with_cursor(): void
    {
        $contact = Contact::factory()->create(['name' => 'Alice', 'phone' => '918888888801']);
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'line_phone' => $this->testLine->phone,
            'contact_name' => $contact->name,
            'last_message_at' => now()->subMinute(),
        ]);

        $this->messageService->recordInbound($conversation, 'Hello there');

        $page = $this->queryService->paginateThreads($this->testLine);

        $this->assertCount(1, $page['items']);
        $this->assertSame($conversation->uuid, $page['items'][0]['uuid']);
        $this->assertSame('Hello there', $page['items'][0]['preview']);
        $this->assertFalse($page['has_more']);
    }

    public function test_it_filters_threads_by_search_and_unread(): void
    {
        $readContact = Contact::factory()->create(['name' => 'Bob', 'phone' => '918888888802']);
        $unreadContact = Contact::factory()->create(['name' => 'Charlie', 'phone' => '918888888803']);

        $readConversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $readContact->id,
            'contact_phone' => $readContact->phone,
            'line_phone' => $this->testLine->phone,
            'contact_name' => $readContact->name,
            'unread_count' => 0,
            'last_message_at' => now(),
        ]);

        $unreadConversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $unreadContact->id,
            'contact_phone' => $unreadContact->phone,
            'line_phone' => $this->testLine->phone,
            'contact_name' => $unreadContact->name,
            'unread_count' => 2,
            'last_message_at' => now()->subMinutes(5),
        ]);

        $this->messageService->sendText($readConversation, 'Read message');
        $this->messageService->recordInbound($unreadConversation, 'Unread message');

        $searchResults = $this->queryService->paginateThreads($this->testLine, search: 'Charlie');
        $this->assertCount(1, $searchResults['items']);
        $this->assertSame($unreadConversation->uuid, $searchResults['items'][0]['uuid']);

        $unreadResults = $this->queryService->paginateThreads($this->testLine, unreadOnly: true);
        $this->assertCount(1, $unreadResults['items']);
        $this->assertSame($unreadConversation->uuid, $unreadResults['items'][0]['uuid']);
    }

    public function test_mark_read_clears_unread_count(): void
    {
        $contact = Contact::factory()->create();
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'line_phone' => $this->testLine->phone,
            'contact_name' => $contact->name,
            'unread_count' => 1,
            'status' => ConversationStatus::Open,
            'last_message_at' => now(),
        ]);

        $this->messageService->recordInbound($conversation, 'Needs read');
        $this->messageService->markRead($conversation->fresh());

        $conversation->refresh();
        $this->assertSame(0, $conversation->unread_count);
        $this->assertSame(
            0,
            Message::query()
                ->where('conversation_id', $conversation->id)
                ->where('direction', MessageDirection::Inbound)
                ->whereNot('status', MessageStatus::Read)
                ->count()
        );
    }
}
