<?php

namespace Tests\Feature\Chatbot;

use App\Domains\Chatbot\Services\ChatbotMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ChatbotMediaUploadTest extends TestCase
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

    public function test_media_upload_returns_tenant_preview_route(): void
    {
        Storage::fake('public');

        $response = $this->actingAsTenantUser()
            ->post('/chatbotfileupload', [
                'file' => UploadedFile::fake()->image('banner.jpg', 100, 100),
                'media_type' => 'image',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $path = $response->json('path');
        $this->assertNotEmpty($path);
        $this->assertStringStartsWith('chatbot/media/', $path);

        $fileUrl = (string) $response->json('fileUrl');
        $this->assertStringContainsString('/automation/chatbot/media/', $fileUrl);
        $this->assertStringNotContainsString('/storage/', $fileUrl);

        Storage::disk('public')->assertExists($path);

        $this->actingAsTenantUser()
            ->get(route('chatbot.media.show', ['path' => $path]))
            ->assertOk();
    }

    public function test_media_service_rejects_paths_outside_chatbot_media(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        app(ChatbotMediaService::class)->stream('../secrets.txt');
    }
}
