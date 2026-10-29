<?php

declare(strict_types=1);

namespace Tests\Unit\Inbox;

use App\Domains\Inbox\Support\InboxInteractivePreview;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InboxInteractivePreviewTest extends TestCase
{
    #[Test]
    public function it_builds_flow_reply_fields_from_nfm_response_json(): void
    {
        $preview = InboxInteractivePreview::build(
            [
                'type' => 'nfm_reply',
                'nfm_reply' => [
                    'name' => 'lead_capture',
                    'body' => 'Sent',
                    'response_json' => json_encode([
                        'flow_token' => 'tok',
                        'full_name' => 'Sparsh Thakur',
                        'email' => 'sparsh@example.com',
                        'interested' => true,
                        'tags' => ['vip', 'demo'],
                    ], JSON_THROW_ON_ERROR),
                ],
            ],
            'Sent',
        );

        $this->assertNotNull($preview);
        $this->assertSame('nfm_reply', $preview['type']);
        $this->assertSame('Form submitted', $preview['body']);
        $this->assertSame('Form submitted', $preview['flow_reply']['title']);
        $this->assertSame('lead_capture', $preview['flow_reply']['flow_name']);
        $this->assertSame(
            [
                ['name' => 'full_name', 'label' => 'Full Name', 'value' => 'Sparsh Thakur'],
                ['name' => 'email', 'label' => 'Email', 'value' => 'sparsh@example.com'],
                ['name' => 'interested', 'label' => 'Interested', 'value' => 'Yes'],
                ['name' => 'tags', 'label' => 'Tags', 'value' => 'vip, demo'],
            ],
            $preview['flow_reply']['fields'],
        );
    }

    #[Test]
    public function it_resolves_nfm_reply_from_raw_message_metadata(): void
    {
        $preview = InboxInteractivePreview::build(
            null,
            'Sent',
            [
                'interactive_reply_type' => 'nfm_reply',
                'raw_message' => [
                    'interactive' => [
                        'nfm_reply' => [
                            'response_json' => '{"city":"Mumbai"}',
                        ],
                    ],
                ],
            ],
        );

        $this->assertNotNull($preview);
        $this->assertSame('Form submitted', $preview['body']);
        $this->assertSame(
            [['name' => 'city', 'label' => 'City', 'value' => 'Mumbai']],
            $preview['flow_reply']['fields'],
        );
    }

    #[Test]
    public function it_keeps_button_preview_without_flow_reply(): void
    {
        $preview = InboxInteractivePreview::build(
            [
                'type' => 'button',
                'body' => ['text' => 'Pick one'],
                'action' => [
                    'buttons' => [
                        ['reply' => ['title' => 'Yes']],
                        ['reply' => ['title' => 'No']],
                    ],
                ],
            ],
            'Pick one',
        );

        $this->assertNotNull($preview);
        $this->assertNull($preview['flow_reply']);
        $this->assertSame('Pick one', $preview['body']);
        $this->assertSame(['Yes', 'No'], $preview['buttons']);
    }
}
