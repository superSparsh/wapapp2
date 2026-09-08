<?php

namespace Tests\Feature\Inbox;

use App\Events\Inbox\InboxMessageCreated;
use App\Events\Inbox\InboxThreadUpdated;
use App\Models\Contact;
use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class InboxRealtimeTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        config([
            'broadcasting.default' => 'log',
            'inbox.realtime_enabled' => true,
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_inbound_message_broadcasts_realtime_events(): void
    {
        Event::fake([InboxMessageCreated::class, InboxThreadUpdated::class]);

        $conversation = $this->createConversation();

        app(\App\Domains\Inbox\Services\InboxMessageService::class)
            ->recordInbound($conversation, 'Realtime hello');

        Event::assertDispatched(InboxMessageCreated::class, function (InboxMessageCreated $event) use ($conversation): bool {
            return $event->conversationUuid === $conversation->uuid
                && $event->message['body'] === 'Realtime hello';
        });
    }

    public function test_mark_read_broadcasts_thread_update(): void
    {
        Event::fake([InboxThreadUpdated::class]);

        $conversation = $this->createConversation();
        app(\App\Domains\Inbox\Services\InboxMessageService::class)
            ->recordInbound($conversation, 'Unread');

        app(\App\Domains\Inbox\Services\InboxMessageService::class)
            ->markRead($conversation);

        Event::assertDispatched(InboxThreadUpdated::class, function (InboxThreadUpdated $event) use ($conversation): bool {
            return $event->conversationUuid === $conversation->uuid
                && ($event->thread['unread'] ?? null) === 0;
        });
    }

    public function test_realtime_is_disabled_when_broadcast_connection_is_null(): void
    {
        config(['broadcasting.default' => 'null']);

        Event::fake([InboxMessageCreated::class]);

        $conversation = $this->createConversation();

        app(\App\Domains\Inbox\Services\InboxMessageService::class)
            ->recordInbound($conversation, 'No broadcast');

        Event::assertNotDispatched(InboxMessageCreated::class);
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
}
