<?php

namespace Tests\Feature\Webhooks;

use App\Domains\Webhooks\Parsers\AlibabaWebhookParser;
use Tests\TestCase;

class AlibabaWebhookParserTest extends TestCase
{
    private AlibabaWebhookParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new AlibabaWebhookParser();
    }

    public function test_parse_payload_with_array_of_items(): void
    {
        $payload = [
            ['MessageId' => 'msg-1', 'From' => '911'],
            ['MessageId' => 'msg-2', 'From' => '922'],
        ];

        $result = $this->parser->parsePayload($payload);

        $this->assertCount(2, $result);
        $this->assertSame('msg-1', $result[0]['MessageId']);
        $this->assertSame('msg-2', $result[1]['MessageId']);
    }

    public function test_parse_payload_with_single_item(): void
    {
        $payload = ['MessageId' => 'msg-single', 'From' => '911'];

        $result = $this->parser->parsePayload($payload);

        $this->assertCount(1, $result);
        $this->assertSame('msg-single', $result[0]['MessageId']);
    }

    public function test_parse_payload_with_json_string(): void
    {
        $payload = json_encode([['MessageId' => 'msg-json', 'From' => '911']]);

        $result = $this->parser->parsePayload($payload);

        $this->assertCount(1, $result);
        $this->assertSame('msg-json', $result[0]['MessageId']);
    }

    public function test_parse_payload_with_quoted_json_array(): void
    {
        // Alibaba sometimes wraps JSON array in extra quotes: "[{...}]"
        // The parser's decodeJsonString strips the outer "[ and ]" quotes
        $inner = '{"MessageId":"msg-quoted","From":"911"}';
        $payload = '"['.$inner.']"';

        $result = $this->parser->parsePayload($payload);

        $this->assertCount(1, $result);
        $this->assertSame('msg-quoted', $result[0]['MessageId']);
    }

    public function test_parse_payload_with_non_array_returns_empty(): void
    {
        $this->assertSame([], $this->parser->parsePayload(42));
        $this->assertSame([], $this->parser->parsePayload(null));
        $this->assertSame([], $this->parser->parsePayload(true));
    }

    public function test_parse_payload_filters_non_array_items_in_list(): void
    {
        $payload = [
            ['MessageId' => 'valid'],
            'not-an-array',
            123,
            ['MessageId' => 'also-valid'],
        ];

        $result = $this->parser->parsePayload($payload);

        $this->assertCount(2, $result);
        $this->assertSame('valid', $result[0]['MessageId']);
        $this->assertSame('also-valid', $result[1]['MessageId']);
    }

    public function test_message_idempotency_key_extracts_message_id(): void
    {
        $item = ['MessageId' => 'wamid.IDEMPOTENT-001', 'From' => '911'];

        $key = $this->parser->messageIdempotencyKey($item);

        $this->assertSame('wamid.IDEMPOTENT-001', $key);
    }

    public function test_message_idempotency_key_returns_null_for_missing(): void
    {
        $this->assertNull($this->parser->messageIdempotencyKey([]));
        $this->assertNull($this->parser->messageIdempotencyKey(['MessageId' => '']));
        $this->assertNull($this->parser->messageIdempotencyKey(['MessageId' => 123]));
    }

    public function test_status_idempotency_key_combines_message_id_and_status(): void
    {
        $item = ['MessageId' => 'wamid.STAT-001', 'Status' => 'Delivered'];

        $key = $this->parser->statusIdempotencyKey($item);

        $this->assertSame('wamid.STAT-001:Delivered', $key);
    }

    public function test_status_idempotency_key_returns_null_if_either_missing(): void
    {
        $this->assertNull($this->parser->statusIdempotencyKey(['MessageId' => 'wamid.X']));
        $this->assertNull($this->parser->statusIdempotencyKey(['Status' => 'Sent']));
        $this->assertNull($this->parser->statusIdempotencyKey(['MessageId' => 'wamid.X', 'Status' => '']));
        $this->assertNull($this->parser->statusIdempotencyKey([]));
    }

    public function test_first_item_returns_first_or_null(): void
    {
        $items = [['MessageId' => 'first'], ['MessageId' => 'second']];
        $this->assertSame(['MessageId' => 'first'], $this->parser->firstItem($items));

        $this->assertNull($this->parser->firstItem([]));
    }
}
