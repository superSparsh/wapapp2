<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Models\Conversation;
use App\Models\Message;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboxApiTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantId = 'tenant-test-123';
    private string $serviceToken = 'default-inbox-service-secret-token';

    protected function setUp(): void
    {
        parent::setUp();
        config(['service-auth.token' => $this->serviceToken]);
    }

    private function serviceHeaders(array $additional = []): array
    {
        return array_merge([
            'X-Service-Token' => $this->serviceToken,
            'X-Tenant-Id' => $this->tenantId,
        ], $additional);
    }

    public function test_health_check_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/health', $this->serviceHeaders());
        $response->assertOk()
            ->assertJsonPath('status', 'healthy')
            ->assertJsonPath('service', 'inbox-service');
    }

    public function test_unauthorized_request_rejected(): void
    {
        $response = $this->getJson('/api/v1/threads', [
            'X-Tenant-Id' => $this->tenantId,
            'X-Service-Token' => 'wrong-token',
        ]);

        $response->assertStatus(401);
    }

    public function test_can_list_threads_and_messages(): void
    {
        app(TenantContext::class)->setTenantId($this->tenantId);

        $conversation = Conversation::query()->create([
            'whatsapp_line_id' => 1,
            'contact_phone' => '919876543210',
            'contact_name' => 'John Doe',
            'last_message_at' => now(),
        ]);

        Message::query()->create([
            'conversation_id' => $conversation->id,
            'body' => 'Hello from customer',
            'direction' => MessageDirection::Inbound,
            'status' => MessageStatus::Delivered,
            'message_type' => MessageType::Text,
        ]);

        $response = $this->getJson('/api/v1/threads?line_id=1', $this->serviceHeaders());

        $response->assertOk()
            ->assertJsonPath('items.0.name', 'John Doe')
            ->assertJsonPath('items.0.preview', 'Hello from customer');

        $messagesResponse = $this->getJson("/api/v1/conversations/{$conversation->uuid}/messages", $this->serviceHeaders());
        $messagesResponse->assertOk()
            ->assertJsonPath('items.0.body', 'Hello from customer');
    }

    public function test_can_send_outbound_text_message(): void
    {
        app(TenantContext::class)->setTenantId($this->tenantId);

        $conversation = Conversation::query()->create([
            'whatsapp_line_id' => 1,
            'contact_phone' => '919876543210',
            'contact_name' => 'John Doe',
            'last_message_at' => now(),
        ]);

        Message::query()->create([
            'conversation_id' => $conversation->id,
            'body' => 'Inbound hello',
            'direction' => MessageDirection::Inbound,
            'status' => MessageStatus::Delivered,
            'message_type' => MessageType::Text,
            'created_at' => now(),
        ]);

        $response = $this->postJson(
            "/api/v1/conversations/{$conversation->uuid}/messages",
            ['body' => 'Outbound reply'],
            $this->serviceHeaders()
        );

        $response->assertStatus(201)
            ->assertJsonPath('message.body', 'Outbound reply')
            ->assertJsonPath('message.direction', 'outbound');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'body' => 'Outbound reply',
            'direction' => 'outbound',
            'status' => 'sent',
        ]);
    }

    public function test_can_record_inbound_message(): void
    {
        $response = $this->postJson('/api/v1/inbound', [
            'line_id' => 1,
            'contact_phone' => '919999999999',
            'contact_name' => 'Alice',
            'body' => 'Hey there',
            'external_message_id' => 'ext_msg_123',
            'message_type' => 'text',
        ], $this->serviceHeaders());

        $response->assertStatus(201)
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('conversations', [
            'tenant_id' => $this->tenantId,
            'contact_phone' => '919999999999',
            'contact_name' => 'Alice',
        ]);

        $this->assertDatabaseHas('messages', [
            'tenant_id' => $this->tenantId,
            'external_message_id' => 'ext_msg_123',
            'body' => 'Hey there',
            'direction' => 'inbound',
        ]);
    }

    public function test_can_mark_conversation_read(): void
    {
        app(TenantContext::class)->setTenantId($this->tenantId);

        $conversation = Conversation::query()->create([
            'whatsapp_line_id' => 1,
            'contact_phone' => '919876543210',
            'unread_count' => 3,
            'last_message_at' => now(),
        ]);

        Message::query()->create([
            'conversation_id' => $conversation->id,
            'body' => 'Inbound 1',
            'direction' => MessageDirection::Inbound,
            'status' => MessageStatus::Delivered,
        ]);

        $response = $this->postJson("/api/v1/conversations/{$conversation->uuid}/read", [], $this->serviceHeaders());
        $response->assertOk()->assertJsonPath('ok', true);

        $conversation->refresh();
        $this->assertSame(0, $conversation->unread_count);
    }
}
