<?php

namespace Tests\Unit\Chatbot;

use App\Domains\Chatbot\Support\FlowNodeDataMapper;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FlowNodeDataMapperTest extends TestCase
{
    private FlowNodeDataMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new FlowNodeDataMapper;
    }

    #[Test]
    public function it_syncs_welcome_message_fields_for_runtime(): void
    {
        $result = $this->mapper->prepareForStorage([
            'nodes' => [
                [
                    'id' => 'welcome_1',
                    'type' => 'welcomeMessage',
                    'data' => [
                        'label' => 'Welcome',
                        'message' => 'Hello there',
                        'keywords' => 'hi, hello',
                    ],
                ],
            ],
            'edges' => [],
        ]);

        $data = $result['nodes'][0]['data'];

        $this->assertSame('Hello there', $data['text']);
        $this->assertSame('hi, hello', $data['triggerKeyword']);
        $this->assertSame('text', $data['messageType']);
    }

    #[Test]
    public function it_converts_interactive_options_into_buttons(): void
    {
        $result = $this->mapper->prepareForStorage([
            'nodes' => [
                [
                    'id' => 'interactive_1',
                    'type' => 'interactiveMessage',
                    'data' => [
                        'label' => 'Menu',
                        'message' => 'Choose one',
                        'interactive_type' => 'button',
                        'options' => ['Yes', 'No'],
                    ],
                ],
            ],
            'edges' => [],
        ]);

        $data = $result['nodes'][0]['data'];

        $this->assertSame('Choose one', $data['bodyText']);
        $this->assertSame('button', $data['interactiveType']);
        $this->assertCount(2, $data['buttons']);
        $this->assertSame('Yes', $data['buttons'][0]['title']);
    }

    #[Test]
    public function it_prepares_editor_data_from_runtime_aliases(): void
    {
        $result = $this->mapper->prepareForEditor([
            'nodes' => [
                [
                    'id' => 'welcome_1',
                    'type' => 'welcomeMessage',
                    'data' => [
                        'text' => 'Runtime text',
                        'triggerKeyword' => 'start',
                    ],
                ],
            ],
            'edges' => [],
        ]);

        $data = $result['nodes'][0]['data'];

        $this->assertSame('Runtime text', $data['message']);
        $this->assertSame('start', $data['keywords']);
    }
}
