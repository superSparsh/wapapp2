<?php

declare(strict_types=1);

namespace Tests\Unit\Inbox;

use App\Domains\Inbox\Services\InboxMediaService;
use App\Domains\Inbox\Support\InboxPresenter;
use App\Enums\MessageDirection;
use App\Enums\MessageType;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class InboxMediaServiceTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private InboxMediaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        Storage::fake((string) config('whatsapp.media.disk', 'public'));
        $this->service = app(InboxMediaService::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_store_from_remote_url_writes_inbound_path(): void
    {
        Http::fake([
            'https://cdn.example.com/*' => Http::response('fake-image-bytes', 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $stored = $this->service->storeFromRemoteUrl(
            'https://cdn.example.com/photo.jpg',
            'customer.jpg',
            'image/jpeg',
        );

        $this->assertStringStartsWith('inbox/inbound/', $stored['path']);
        $this->assertStringContainsString('/inbox/media/', $stored['url']);
        Storage::disk((string) config('whatsapp.media.disk', 'public'))->assertExists($stored['path']);
    }

    public function test_persist_inbound_message_media_sets_local_path_and_display_url(): void
    {
        Http::fake([
            'https://cdn.example.com/*' => Http::response('inbound-bytes', 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);

        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
        ]);

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'body' => 'photo',
            'direction' => MessageDirection::Inbound,
            'message_type' => MessageType::Image,
            'status' => \App\Enums\MessageStatus::Delivered,
            'metadata' => [
                'media_url' => 'https://cdn.example.com/inbound.png',
                'file_name' => 'inbound.png',
            ],
        ]);

        $this->assertTrue($this->service->persistInboundMessageMedia($message));

        $message->refresh();
        $metadata = $message->metadata;

        $this->assertSame('https://cdn.example.com/inbound.png', $metadata['media_url_remote'] ?? null);
        $this->assertStringStartsWith('inbox/inbound/', (string) ($metadata['media_path'] ?? ''));
        $this->assertStringContainsString('/inbox/media/', (string) ($metadata['media_url_local'] ?? ''));

        $display = InboxPresenter::displayMediaUrl($metadata);
        $this->assertNotNull($display);
        $this->assertStringContainsString('/inbox/media/inbox/inbound/', $display);
        $this->assertStringNotContainsString('cdn.example.com', $display);
    }

    public function test_display_url_prefers_inbound_media_path(): void
    {
        $url = InboxPresenter::displayMediaUrl([
            'media_url' => 'https://cdn.example.com/remote.jpg',
            'media_path' => 'inbox/inbound/abc.jpg',
        ]);

        $this->assertNotNull($url);
        $this->assertStringContainsString('/inbox/media/inbox/inbound/abc.jpg', $url);
    }
}
