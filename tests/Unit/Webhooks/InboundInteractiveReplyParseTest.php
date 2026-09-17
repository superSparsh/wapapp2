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
}
