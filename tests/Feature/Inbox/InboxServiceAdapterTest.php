<?php

declare(strict_types=1);

namespace Tests\Feature\Inbox;

use App\Domains\Inbox\Services\InboxMessageService;
use App\Models\Contact;
use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class InboxServiceAdapterTest extends TestCase
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

    public function test_threads_api_uses_local_monolith(): void
    {
        $contact = Contact::factory()->create(['name' => 'Direct Local Contact']);
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'line_phone' => $this->testLine->phone,
            'contact_name' => $contact->name,
            'last_message_at' => now(),
        ]);
        app(InboxMessageService::class)->recordInbound($conversation, 'Direct local text');

        $this->actingAsTenantUser()
            ->getJson(route('inbox.api.threads'))
            ->assertOk()
            ->assertJsonPath('items.0.name', 'Direct Local Contact')
            ->assertJsonPath('items.0.preview', 'Direct local text');
    }
}
