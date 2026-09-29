<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Domains\Notifications\Jobs\SendInboxFcmPushJob;
use App\Domains\Notifications\Services\FcmClient;
use App\Domains\Notifications\Services\FcmPushService;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Models\Conversation;
use App\Models\FcmToken;
use App\Models\Message;
use App\Models\TenantUserAccess;
use App\Enums\TenantUserAccountType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class FcmPushTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();

        // Ensure tenant migration for fcm_tokens is applied (RefreshDatabase + tenancy).
        if (! \Illuminate\Support\Facades\Schema::hasTable('fcm_tokens')) {
            $this->artisan('tenants:migrate', [
                '--tenants' => [$this->testTenant->id],
                '--force' => true,
            ]);
        }
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_web_user_can_register_and_revoke_device_token(): void
    {
        $this->actingAsTenantUser()
            ->postJson(route('inbox.api.device-token.store'), [
                'token' => str_repeat('a', 40),
                'platform' => 'android',
                'device_id' => 'pixel-1',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('fcm_tokens', [
            'user_id' => $this->testUser->id,
            'token' => str_repeat('a', 40),
            'platform' => 'android',
        ]);

        $this->actingAsTenantUser()
            ->deleteJson(route('inbox.api.device-token.destroy'), [
                'token' => str_repeat('a', 40),
            ])
            ->assertOk()
            ->assertJsonPath('deleted', 1);

        $this->assertSoftDeleted('fcm_tokens', [
            'token' => str_repeat('a', 40),
        ]);
    }

    public function test_api_token_can_register_device_token(): void
    {
        $apiToken = 'test-api-'.Str::random(24);

        $this->testUser->forceFill(['api_token' => $apiToken])->save();

        TenantUserAccess::query()->updateOrCreate(
            ['email' => strtolower($this->testUser->email)],
            [
                'tenant_id' => $this->testTenant->id,
                'account_type' => TenantUserAccountType::Owner,
                'is_active' => true,
                'api_token' => $apiToken,
                'phone' => $this->testUser->phone,
            ],
        );

        tenancy()->end();

        $this->postJson('/api/v1/mobile/device-token', [
            'token' => str_repeat('b', 40),
            'platform' => 'ios',
        ], [
            'Authorization' => 'Bearer '.$apiToken,
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        tenancy()->initialize($this->testTenant);
        $this->assertDatabaseHas('fcm_tokens', [
            'user_id' => $this->testUser->id,
            'token' => str_repeat('b', 40),
            'platform' => 'ios',
        ]);
    }

    public function test_inbound_message_dispatches_fcm_job_without_breaking(): void
    {
        Bus::fake([SendInboxFcmPushJob::class]);

        $this->mock(FcmClient::class, function ($mock): void {
            $mock->shouldReceive('isReady')->andReturn(true);
            $mock->shouldReceive('sendToToken')->andReturn(true);
        });

        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'assigned_user_id' => $this->testUser->id,
        ]);

        $message = app(\App\Domains\Inbox\Services\InboxMessageService::class)
            ->recordInbound($conversation, 'Hello from customer', 'wamid.FCM-TEST-1');

        $this->assertInstanceOf(Message::class, $message);

        Bus::assertDispatched(SendInboxFcmPushJob::class, function (SendInboxFcmPushJob $job) use ($conversation, $message): bool {
            return $job->tenantId === (string) $this->testTenant->id
                && $job->conversationId === (int) $conversation->id
                && $job->messageId === (int) $message->id;
        });
    }

    public function test_inbound_message_skips_fcm_dispatch_when_not_ready(): void
    {
        Bus::fake([SendInboxFcmPushJob::class]);

        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'assigned_user_id' => $this->testUser->id,
        ]);

        app(\App\Domains\Inbox\Services\InboxMessageService::class)
            ->recordInbound($conversation, 'Hello', 'wamid.FCM-SKIP-1');

        Bus::assertNotDispatched(SendInboxFcmPushJob::class);
    }

    public function test_fcm_push_is_noop_without_credentials(): void
    {
        config(['fcm.enabled' => true, 'fcm.credentials' => storage_path('app/missing-firebase.json')]);

        FcmToken::query()->create([
            'user_id' => $this->testUser->id,
            'token' => str_repeat('c', 40),
            'platform' => 'android',
        ]);

        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'assigned_user_id' => $this->testUser->id,
            'contact_name' => 'Test Contact',
        ]);

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::Inbound,
            'message_type' => MessageType::Text,
            'status' => MessageStatus::Delivered,
            'body' => 'Ping',
        ]);

        Http::fake();

        app(FcmPushService::class)->sendNewMessageNotification($conversation, $message);

        Http::assertNothingSent();
        $this->assertFalse(app(FcmClient::class)->isReady());
    }
}
