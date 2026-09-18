<?php

declare(strict_types=1);

namespace Tests\Unit\Inbox;

use App\Domains\Inbox\Services\CamsOutboundPayloadBuilder;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsappLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CamsOutboundPayloadBuilderTest extends TestCase
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

    public function test_template_params_nested_body_are_flattened(): void
    {
        $line = $this->testLine;
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $line->id,
            'line_phone' => $line->phone,
            'contact_phone' => '918888888900',
        ]);

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'body' => 'Hi',
            'direction' => MessageDirection::Outbound,
            'message_type' => MessageType::Template,
            'status' => MessageStatus::Queued,
            'metadata' => [
                'template_code' => '1234567890123456',
                'language' => 'en_GB',
                'template_params' => [
                    'body' => [
                        'full_name' => 'Ada Lovelace',
                    ],
                ],
            ],
        ]);

        $payload = app(CamsOutboundPayloadBuilder::class)->build(
            $message,
            $conversation,
            $line,
        );

        $this->assertSame('template', $payload['Type']);
        $this->assertSame('1234567890123456', $payload['TemplateCode']);
        $this->assertSame(
            ['full_name' => 'Ada Lovelace'],
            json_decode($payload['TemplateParams'], true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function test_media_payload_uses_metadata_link(): void
    {
        $line = $this->testLine;
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $line->id,
            'line_phone' => $line->phone,
            'contact_phone' => '918888888901',
        ]);

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'body' => 'Caption',
            'direction' => MessageDirection::Outbound,
            'message_type' => MessageType::Image,
            'status' => MessageStatus::Queued,
            'metadata' => [
                'media_url' => 'https://bucket.oss-ap-southeast-1.aliyuncs.com/path/photo.jpg',
                'file_name' => 'photo.jpg',
            ],
        ]);

        $payload = app(CamsOutboundPayloadBuilder::class)->build(
            $message,
            $conversation,
            $line,
        );

        $content = json_decode($payload['Content'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('image', $payload['MessageType']);
        $this->assertSame('https://bucket.oss-ap-southeast-1.aliyuncs.com/path/photo.jpg', $content['link']);
        $this->assertSame('Caption', $content['text']);
    }
}
