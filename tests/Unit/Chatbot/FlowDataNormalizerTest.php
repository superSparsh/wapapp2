<?php

namespace Tests\Unit\Chatbot;

use App\Domains\Chatbot\Services\FlowDataNormalizer;
use App\Models\ChatbotFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class FlowDataNormalizerTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private FlowDataNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->normalizer = app(FlowDataNormalizer::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_normalizes_reactflow_format(): void
    {
        $flow = ChatbotFlow::factory()->create([
            'exported_data' => [
                'nodes' => [
                    ['id' => 'n1', 'type' => 'welcomeMessage', 'data' => ['message' => 'Hello']],
                    ['id' => 'n2', 'type' => 'delay', 'data' => ['delay_seconds' => 5]],
                ],
                'edges' => [
                    ['id' => 'e1', 'source' => 'n1', 'target' => 'n2', 'sourceHandle' => 'output_1'],
                ],
            ],
        ]);

        $result = $this->normalizer->normalize($flow);

        $this->assertArrayHasKey('n1', $result);
        $this->assertArrayHasKey('n2', $result);
        $this->assertSame('welcomeMessage', $result['n1']['class']);
        $this->assertSame('Hello', $result['n1']['data']['message']);
    }

    public function test_normalizes_drawflow_format(): void
    {
        $flow = ChatbotFlow::factory()->create([
            'exported_data' => [
                'drawflow' => [
                    'Home' => [
                        'data' => [
                            'node_1' => [
                                'id' => 1,
                                'class' => 'welcomeMessage',
                                'data' => ['message' => 'Hi there'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $result = $this->normalizer->normalize($flow);

        $this->assertNotEmpty($result);
    }

    public function test_returns_empty_for_null_data(): void
    {
        $flow = ChatbotFlow::factory()->create(['exported_data' => null]);

        $result = $this->normalizer->normalize($flow);

        $this->assertEmpty($result);
    }

    public function test_indexes_edges_by_source(): void
    {
        $flow = ChatbotFlow::factory()->create([
            'exported_data' => [
                'nodes' => [
                    ['id' => 'n1', 'type' => 'welcomeMessage', 'data' => []],
                    ['id' => 'n2', 'type' => 'delay', 'data' => []],
                ],
                'edges' => [
                    ['id' => 'e1', 'source' => 'n1', 'target' => 'n2', 'sourceHandle' => 'output_1'],
                ],
            ],
        ]);

        $result = $this->normalizer->normalize($flow);

        $this->assertArrayHasKey('outputs', $result['n1']);
    }
}
