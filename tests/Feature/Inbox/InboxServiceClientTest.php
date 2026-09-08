<?php

declare(strict_types=1);

namespace Tests\Feature\Inbox;

use App\Domains\Inbox\Contracts\InboxServiceClientInterface;
use App\Domains\Inbox\Services\InboxServiceClient;
use App\Models\Contact;
use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class InboxServiceClientTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private InboxServiceClientInterface $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->client = app(InboxServiceClientInterface::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_health_check_returns_true_when_healthy(): void
    {
        Http::fake([
            '*/health' => Http::response(['status' => 'healthy'], 200),
        ]);

        $this->assertTrue($this->client->isHealthy());
    }

    public function test_get_threads_sends_correct_headers_and_params(): void
    {
        Http::fake([
            '*/threads*' => Http::response([
                'items' => [
                    ['uuid' => 'conv-123', 'name' => 'John Doe', 'preview' => 'Hello'],
                ],
                'next_cursor' => null,
                'has_more' => false,
            ], 200),
        ]);

        $result = $this->client->getThreads((int) $this->testLine->id, ['unread_only' => true]);

        $this->assertCount(1, $result['items']);
        $this->assertSame('John Doe', $result['items'][0]['name']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/threads')
                && $request->hasHeader('X-Service-Token')
                && $request->hasHeader('X-Tenant-Id');
        });
    }

    public function test_send_message_calls_microservice_endpoint(): void
    {
        $contact = Contact::factory()->create();
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
        ]);

        Http::fake([
            '*/conversations/*/messages' => Http::response([
                'message' => [
                    'uuid' => 'msg-123',
                    'body' => 'Hello from microservice',
                    'direction' => 'outbound',
                    'status' => 'queued',
                ],
            ], 201),
        ]);

        $result = $this->client->sendMessage($conversation->uuid, 'Hello from microservice');

        $this->assertSame('msg-123', $result['message']['uuid']);
        $this->assertSame('Hello from microservice', $result['message']['body']);
    }
}
