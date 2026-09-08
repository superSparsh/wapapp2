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

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private string $serviceToken = 'default-inbox-service-secret-token';

    protected function setUp(): void
    {
        parent::setUp();
        config(['service-auth.token' => $this->serviceToken]);
    }

    public function test_tenant_cannot_see_other_tenant_threads(): void
    {
        // Tenant A creates conversation
        app(TenantContext::class)->setTenantId('tenant-A');
        $convA = Conversation::query()->create([
            'whatsapp_line_id' => 1,
            'contact_phone' => '911111111111',
            'contact_name' => 'User A',
            'last_message_at' => now(),
        ]);
        Message::query()->create([
            'conversation_id' => $convA->id,
            'body' => 'Secret A',
            'direction' => MessageDirection::Inbound,
            'status' => MessageStatus::Delivered,
            'message_type' => MessageType::Text,
        ]);

        // Tenant B creates conversation
        app(TenantContext::class)->setTenantId('tenant-B');
        $convB = Conversation::query()->create([
            'whatsapp_line_id' => 1,
            'contact_phone' => '912222222222',
            'contact_name' => 'User B',
            'last_message_at' => now(),
        ]);
        Message::query()->create([
            'conversation_id' => $convB->id,
            'body' => 'Secret B',
            'direction' => MessageDirection::Inbound,
            'status' => MessageStatus::Delivered,
            'message_type' => MessageType::Text,
        ]);

        // Request as Tenant A
        $responseA = $this->getJson('/api/v1/threads?line_id=1', [
            'X-Service-Token' => $this->serviceToken,
            'X-Tenant-Id' => 'tenant-A',
        ]);

        $responseA->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.name', 'User A');

        // Request as Tenant B
        $responseB = $this->getJson('/api/v1/threads?line_id=1', [
            'X-Service-Token' => $this->serviceToken,
            'X-Tenant-Id' => 'tenant-B',
        ]);

        $responseB->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.name', 'User B');

        // Tenant A trying to access Tenant B's conversation directly gets 404
        $crossTenantResponse = $this->getJson("/api/v1/conversations/{$convB->uuid}/messages", [
            'X-Service-Token' => $this->serviceToken,
            'X-Tenant-Id' => 'tenant-A',
        ]);

        $crossTenantResponse->assertStatus(404);
    }
}
