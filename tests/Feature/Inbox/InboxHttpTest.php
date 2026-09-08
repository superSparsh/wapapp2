<?php

namespace Tests\Feature\Inbox;

use App\Domains\Inbox\Services\InboxMessageService;
use App\Models\Contact;
use App\Models\Conversation;
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

        $this->messageService->recordInbound($conversation, 'Inbound hello');

        $this->actingAsTenantUser()
            ->get(route('inbox.show', $conversation))
            ->assertOk()
            ->assertSee('Inbox User')
            ->assertSee('Inbound hello');
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
            ->assertJsonPath('items.0.preview', 'API preview');
    }
}
