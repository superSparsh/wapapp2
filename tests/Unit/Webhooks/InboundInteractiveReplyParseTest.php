<?php

declare(strict_types=1);

namespace Tests\Unit\Webhooks;

use App\Domains\Webhooks\Handlers\InboundMessageHandler;
use ReflectionMethod;
use Tests\TestCase;

class InboundInteractiveReplyParseTest extends TestCase
{
    /**
     * @return array{body: string, is_interactive: bool, metadata: array<string, mixed>}
     */
    private function parse(array $item): array
    {
        $handler = app(InboundMessageHandler::class);
        $method = new ReflectionMethod(InboundMessageHandler::class, 'parseInboundMessage');
        $method->setAccessible(true);

        /** @var array{body: string, is_interactive: bool, metadata: array<string, mixed>} $result */
        $result = $method->invoke($handler, $item);

        return $result;
    }

    public function test_parses_button_reply_title_and_id(): void
    {
        $result = $this->parse([
            'Type' => 'INTERACTIVE',
            'Message' => json_encode([
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'button_reply',
                    'button_reply' => [
                        'id' => 'btn_sales',
                        'title' => 'Sales',
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
        ]);

        $this->assertTrue($result['is_interactive']);
        $this->assertSame('Sales', $result['body']);
        $this->assertSame('btn_sales', $result['metadata']['interactive_reply_id']);
        $this->assertSame('button_reply', $result['metadata']['interactive_reply_type']);
        $this->assertIsArray($result['metadata']['interactive']);
    }

    public function test_parses_list_reply_title_and_id(): void
    {
        $result = $this->parse([
            'Type' => 'INTERACTIVE',
            'Message' => json_encode([
                'interactive' => [
                    'type' => 'list_reply',
                    'list_reply' => [
                        'id' => 'row_b',
                        'title' => 'Option B',
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
        ]);

        $this->assertTrue($result['is_interactive']);
        $this->assertSame('Option B', $result['body']);
        $this->assertSame('row_b', $result['metadata']['interactive_reply_id']);
    }

    public function test_plain_text_message_unchanged(): void
    {
        $result = $this->parse([
            'Type' => 'TEXT',
            'Message' => 'hello world',
        ]);

        $this->assertFalse($result['is_interactive']);
        $this->assertSame('hello world', $result['body']);
        $this->assertSame([], $result['metadata']);
    }

    public function test_json_text_payload_extracts_text_field(): void
    {
        $result = $this->parse([
            'Type' => 'TEXT',
            'Message' => json_encode(['text' => 'hi there'], JSON_THROW_ON_ERROR),
        ]);

        $this->assertFalse($result['is_interactive']);
        $this->assertSame('hi there', $result['body']);
    }

    public function test_nested_cloud_api_text_body_is_extracted(): void
    {
        $result = $this->parse([
            'Type' => 'TEXT',
            'Message' => json_encode([
                'type' => 'text',
                'text' => [
                    'body' => 'Need pricing',
                ],
            ], JSON_THROW_ON_ERROR),
        ]);

        $this->assertFalse($result['is_interactive']);
        $this->assertSame('Need pricing', $result['body']);
    }

    public function test_message_object_payload_extracts_text_body(): void
    {
        $result = $this->parse([
            'Type' => 'TEXT',
            'From' => '918888800001',
            'Message' => [
                'text' => [
                    'body' => 'Hello from object',
                ],
            ],
        ]);

        $this->assertFalse($result['is_interactive']);
        $this->assertSame('Hello from object', $result['body']);
    }

    public function test_image_message_extracts_media_url_and_caption(): void
    {
        $result = $this->parse([
            'Type' => 'IMAGE',
            'Message' => json_encode([
                'link' => 'https://cdn.example.com/photo.jpg',
                'text' => 'Look at this',
            ], JSON_THROW_ON_ERROR),
        ]);

        $this->assertFalse($result['is_interactive']);
        $this->assertSame('Look at this', $result['body']);
        $this->assertSame('https://cdn.example.com/photo.jpg', $result['metadata']['media_url']);
    }

    public function test_document_message_extracts_link_and_filename(): void
    {
        $result = $this->parse([
            'Type' => 'DOCUMENT',
            'Message' => json_encode([
                'link' => 'https://cdn.example.com/invoice.pdf',
                'fileName' => 'invoice.pdf',
                'fileType' => 'application/pdf',
            ], JSON_THROW_ON_ERROR),
        ]);

        $this->assertSame('https://cdn.example.com/invoice.pdf', $result['metadata']['media_url']);
        $this->assertSame('invoice.pdf', $result['metadata']['file_name']);
        $this->assertSame('application/pdf', $result['metadata']['file_type']);
    }

    public function test_plain_image_url_becomes_media_url(): void
    {
        $result = $this->parse([
            'Type' => 'IMAGE',
            'Message' => 'https://cdn.example.com/legacy-style.jpg',
        ]);

        $this->assertSame('https://cdn.example.com/legacy-style.jpg', $result['metadata']['media_url']);
        $this->assertSame('https://cdn.example.com/legacy-style.jpg', $result['body']);
    }

    public function test_location_message_extracts_coordinates(): void
    {
        $result = $this->parse([
            'Type' => 'LOCATION',
            'Message' => json_encode([
                'latitude' => '28.6139',
                'longitude' => '77.2090',
            ], JSON_THROW_ON_ERROR),
        ]);

        $this->assertSame(28.6139, $result['metadata']['latitude']);
        $this->assertSame(77.2090, $result['metadata']['longitude']);
    }
}
