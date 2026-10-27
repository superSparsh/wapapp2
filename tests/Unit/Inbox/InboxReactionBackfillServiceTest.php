<?php

declare(strict_types=1);

namespace Tests\Unit\Inbox;

use App\Domains\Inbox\Services\InboxReactionBackfillService;
use App\Enums\MessageDirection;
use App\Enums\MessageType;
use App\Models\Message;
use Tests\TestCase;

class InboxReactionBackfillServiceTest extends TestCase
{
    public function test_rewrites_legacy_reaction_placeholder_from_raw_message(): void
    {
        $service = new InboxReactionBackfillService;
        $message = new Message([
            'direction' => MessageDirection::Inbound,
            'message_type' => MessageType::Text,
            'body' => '[REACTION message]',
            'metadata' => [
                'raw_message' => [
                    'emoji' => '👍',
                    'messageId' => 'wamid.abc',
                ],
            ],
        ]);

        $result = $service->rewriteMessage($message);

        $this->assertTrue($result['updated']);
        $this->assertSame('👍', $message->body);
        $this->assertSame(MessageType::Reaction, $message->message_type);
        $this->assertSame('👍', $message->metadata['reaction_emoji']);
        $this->assertSame('wamid.abc', $message->metadata['reacted_message_id']);
    }

    public function test_rewrites_nested_cloud_reaction(): void
    {
        $service = new InboxReactionBackfillService;
        $message = new Message([
            'direction' => MessageDirection::Inbound,
            'message_type' => MessageType::Text,
            'body' => '[REACTION message]',
            'metadata' => [
                'raw_message' => [
                    'type' => 'reaction',
                    'reaction' => [
                        'emoji' => '❤️',
                        'message_id' => 'wamid.target',
                    ],
                ],
            ],
        ]);

        $result = $service->rewriteMessage($message);

        $this->assertTrue($result['updated']);
        $this->assertSame('❤️', $message->body);
        $this->assertSame('wamid.target', $message->metadata['reacted_message_id']);
    }

    public function test_skips_when_raw_message_missing(): void
    {
        $service = new InboxReactionBackfillService;
        $message = new Message([
            'direction' => MessageDirection::Inbound,
            'message_type' => MessageType::Text,
            'body' => '[REACTION message]',
            'metadata' => [],
        ]);

        $result = $service->rewriteMessage($message);

        $this->assertFalse($result['updated']);
        $this->assertSame('no_recoverable_payload', $result['reason']);
        $this->assertSame('[REACTION message]', $message->body);
    }

    public function test_skips_already_rewritten(): void
    {
        $service = new InboxReactionBackfillService;
        $message = new Message([
            'direction' => MessageDirection::Inbound,
            'message_type' => MessageType::Reaction,
            'body' => '😂',
            'metadata' => [
                'reaction_emoji' => '😂',
                'reacted_message_id' => 'wamid.x',
            ],
        ]);

        $result = $service->rewriteMessage($message);

        $this->assertFalse($result['updated']);
        $this->assertSame('already_ok', $result['reason']);
    }
}
