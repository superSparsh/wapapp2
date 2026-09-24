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

    public function test_contact_payload_is_single_object_with_name(): void
    {
        $line = $this->testLine;
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $line->id,
            'line_phone' => $line->phone,
            'contact_phone' => '918888888902',
        ]);

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'body' => 'Ada Lovelace',
            'direction' => MessageDirection::Outbound,
            'message_type' => MessageType::Contact,
            'status' => MessageStatus::Queued,
            'metadata' => [
                'contacts' => [[
                    'name' => [
                        'formatted_name' => 'Ada Lovelace',
                        // Intentionally omit first/last — builder must derive them.
                    ],
                    'phones' => [[
                        'phone' => '+91 88888 88902',
                        'type' => 'CELL',
                    ]],
                    'emails' => [[
                        'email' => 'ada@example.com',
                        'type' => 'work',
                    ]],
                    'org' => [
                        'company' => 'Analytical Engine Co',
                    ],
                ]],
            ],
        ]);

        $payload = app(CamsOutboundPayloadBuilder::class)->build(
            $message,
            $conversation,
            $line,
        );

        $content = json_decode($payload['Content'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('contacts', $payload['MessageType']);
        $this->assertSame('message', $payload['Type']);
        $this->assertArrayNotHasKey('Language', $payload);
        // Alibaba: Content must include top-level `name` (single contact object).
        $this->assertIsArray($content);
        $this->assertArrayHasKey('name', $content);
        $this->assertArrayNotHasKey('contacts', $content);
        $this->assertArrayNotHasKey(0, $content);
        $this->assertSame('Ada Lovelace', $content['name']['formatted_name']);
        $this->assertSame('Ada', $content['name']['first_name']);
        $this->assertSame('Lovelace', $content['name']['last_name']);
        $this->assertSame('918888888902', $content['phones'][0]['phone']);
        $this->assertSame('918888888902', $content['phones'][0]['wa_id']);
        $this->assertSame('CELL', $content['phones'][0]['type']);
        // CAMS Content stays minimal (name + phones only).
        $this->assertArrayNotHasKey('emails', $content);
        $this->assertArrayNotHasKey('org', $content);
    }
}
