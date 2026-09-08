<?php

namespace Tests\Feature;

use App\Domains\Inbox\Services\InboxMessageService;
use App\Models\Contact;
use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
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

    public function test_global_search_returns_pages_and_conversations(): void
    {
        $contact = Contact::factory()->create(['name' => 'Navbar Contact', 'phone' => '919999999901']);
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'line_phone' => $this->testLine->phone,
            'contact_name' => $contact->name,
            'last_message_at' => now(),
        ]);

        $this->messageService->recordInbound($conversation, 'Navbar preview');

        $this->actingAsTenantUser()
            ->getJson(route('search', ['q' => 'Navbar']))
            ->assertOk()
            ->assertJsonPath('conversations.0.name', 'Navbar Contact')
            ->assertJsonPath('conversations.0.preview', 'Navbar preview');

        $this->actingAsTenantUser()
            ->getJson(route('search', ['q' => 'Inbox']))
            ->assertOk()
            ->assertJsonFragment(['label' => 'Inbox']);
    }

    public function test_global_search_requires_authentication(): void
    {
        $this->getJson(route('search', ['q' => 'test']))->assertUnauthorized();
    }
}
