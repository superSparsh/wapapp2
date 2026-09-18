<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use App\Domains\Templates\Support\InteractiveMessagePayloadBuilder;
use App\Models\InteractiveMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class InteractiveMessagePayloadBuilderTest extends TestCase
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

    public function test_builds_button_interactive_payload(): void
    {
        $message = InteractiveMessage::query()->create([
            'name' => 'Order Ready',
            'type' => 'button',
            'content' => [
                'body' => 'Your order is ready',
                'footer' => 'Reply below',
                'buttons' => [
                    ['id' => 'yes', 'text' => 'Yes'],
                    ['id' => 'no', 'text' => 'No'],
                ],
            ],
            'whatsapp_line_id' => $this->testLine->id,
        ]);

        $payload = app(InteractiveMessagePayloadBuilder::class)->forMessage($message);

        $this->assertSame('button', $payload['type']);
        $this->assertSame('Your order is ready', $payload['body']['text']);
        $this->assertSame('Reply below', $payload['footer']['text']);
        $this->assertCount(2, $payload['action']['buttons']);
        $this->assertSame('reply', $payload['action']['buttons'][0]['type']);
        $this->assertSame('yes', $payload['action']['buttons'][0]['reply']['id']);
        $this->assertSame('Yes', $payload['action']['buttons'][0]['reply']['title']);
    }

    public function test_builds_list_interactive_payload(): void
    {
        $payload = app(InteractiveMessagePayloadBuilder::class)->fromFlat('list', [
            'body' => 'Pick a service',
            'list_button_text' => 'Options',
            'list_sections' => [
                [
                    'title' => 'Support',
                    'rows' => [
                        ['id' => 'billing', 'title' => 'Billing', 'description' => 'Invoices'],
                        ['title' => 'Tech', 'description' => 'Issues'],
                    ],
                ],
            ],
        ]);

        $this->assertSame('list', $payload['type']);
        $this->assertSame('Options', $payload['action']['button']);
        $this->assertSame('billing', $payload['action']['sections'][0]['rows'][0]['id']);
        $this->assertSame('row_0_1', $payload['action']['sections'][0]['rows'][1]['id']);
    }

    public function test_from_node_data_uses_existing_api_shape(): void
    {
        $payload = app(InteractiveMessagePayloadBuilder::class)->fromNodeData([
            'type' => 'button',
            'body' => ['text' => 'Hello $(name)'],
            'action' => [
                'buttons' => [
                    ['type' => 'reply', 'reply' => ['id' => 'a', 'title' => 'A']],
                ],
            ],
        ], fn (string $text): string => str_replace('$(name)', 'Sparsh', $text));

        $this->assertSame('Hello Sparsh', $payload['body']['text']);
        $this->assertSame('A', $payload['action']['buttons'][0]['reply']['title']);
    }

    public function test_library_content_includes_ui_and_api_keys(): void
    {
        $message = InteractiveMessage::query()->create([
            'name' => 'Menu',
            'type' => 'button',
            'content' => [
                'body' => 'Choose',
                'buttons' => [['text' => 'Sales']],
            ],
            'whatsapp_line_id' => $this->testLine->id,
        ]);

        $content = app(InteractiveMessagePayloadBuilder::class)->toLibraryContent($message);

        $this->assertSame('button', $content['interactiveType']);
        $this->assertSame('Choose', $content['bodyText']);
        $this->assertSame('Sales', $content['buttons'][0]['title']);
        $this->assertSame('button', $content['type']);
        $this->assertArrayHasKey('action', $content);
    }

    public function test_builds_cta_url_and_location_request_payloads(): void
    {
        $cta = app(InteractiveMessagePayloadBuilder::class)->fromFlat('cta_url', [
            'body' => 'Visit us',
            'button_text' => 'Shop Now',
            'url' => 'https://example.com',
        ]);

        $this->assertSame('cta_url', $cta['type']);
        $this->assertSame('Shop Now', $cta['action']['parameters']['display_text']);
        $this->assertSame('https://example.com', $cta['action']['parameters']['url']);

        $location = app(InteractiveMessagePayloadBuilder::class)->fromFlat('location_request_message', [
            'body' => 'Share your location',
        ]);

        $this->assertSame('location_request_message', $location['type']);
        $this->assertSame('send_location', $location['action']['name']);
    }
}
