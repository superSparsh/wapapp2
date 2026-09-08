<?php

declare(strict_types=1);

namespace Tests\Feature\Inbox;

use App\Domains\Inbox\Services\InboxMessageService;
use App\Models\Contact;
use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    public function test_when_microservice_enabled_threads_api_calls_microservice(): void
    {
        config([
            'inbox-service.enabled' => true,
            'inbox-service.base_url' => 'http://127.0.0.1:8001/api/v1',
        ]);

        Http::fake([
            '*/threads*' => Http::response([
                'items' => [
                    ['uuid' => 'ms-conv-1', 'name' => 'Microservice Contact', 'preview' => 'From Microservice'],
                ],
                'next_cursor' => null,
                'has_more' => false,
            ], 200),
        ]);

        $this->actingAsTenantUser()
            ->getJson(route('inbox.api.threads'))
            ->assertOk()
            ->assertJsonPath('items.0.name', 'Microservice Contact')
            ->assertJsonPath('items.0.preview', 'From Microservice');
    }

    public function test_when_microservice_fails_gracefully_falls_back_to_local(): void
    {
        config([
            'inbox-service.enabled' => true,
            'inbox-service.fallback_to_local' => true,
            'inbox-service.base_url' => 'http://127.0.0.1:8001/api/v1',
        ]);

        // Create local conversation & message
        $contact = Contact::factory()->create(['name' => 'Local Fallback Contact']);
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'line_phone' => $this->testLine->phone,
            'contact_name' => $contact->name,
            'last_message_at' => now(),
        ]);
        app(InboxMessageService::class)->recordInbound($conversation, 'Local preview text');

        // Simulate microservice failure
        Http::fake([
            '*/threads*' => Http::response(['error' => 'Internal Server Error'], 500),
        ]);

        // Request should still succeed via local fallback
        $this->actingAsTenantUser()
            ->getJson(route('inbox.api.threads'))
            ->assertOk()
            ->assertJsonPath('items.0.name', 'Local Fallback Contact')
            ->assertJsonPath('items.0.preview', 'Local preview text');
    }

    public function test_when_microservice_disabled_uses_local_directly(): void
    {
        config([
            'inbox-service.enabled' => false,
        ]);

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
