<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Domains\Templates\Contracts\TemplateServiceClientInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TemplateServiceClientTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private TemplateServiceClientInterface $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->client = app(TemplateServiceClientInterface::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_health_check_returns_true_when_healthy(): void
    {
        Http::fake([
            '*health*' => Http::response(['status' => 'healthy', 'service' => 'template-service'], 200),
        ]);

        $this->assertTrue($this->client->isHealthy());
    }

    public function test_list_templates_sends_correct_headers(): void
    {
        Http::fake([
            '*templates*' => Http::response([
                'items' => [
                    ['uuid' => 'tpl-123', 'name' => 'Flash Sale Alert', 'category' => 'MARKETING', 'status' => 'approved'],
                ],
                'meta' => ['total' => 1, 'current_page' => 1, 'per_page' => 10],
            ], 200),
        ]);

        $result = $this->client->listTemplates(['q' => 'Flash']);

        $this->assertCount(1, $result['items']);
        $this->assertSame('Flash Sale Alert', $result['items'][0]['name']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/templates')
                && $request->hasHeader('X-Service-Token')
                && $request->hasHeader('X-Tenant-Id');
        });
    }

    public function test_create_draft_calls_microservice(): void
    {
        Http::fake([
            '*templates/draft' => Http::response([
                'template' => [
                    'uuid' => 'tpl-draft-uuid',
                    'name' => 'draft_template_1',
                    'status' => 'draft',
                ],
                'message' => 'Draft template created successfully.',
            ], 201),
        ]);

        $result = $this->client->createDraft();

        $this->assertSame('tpl-draft-uuid', $result['template']['uuid']);
        $this->assertSame('draft_template_1', $result['template']['name']);
    }

    public function test_preview_calls_microservice(): void
    {
        Http::fake([
            '*templates/preview/*' => Http::response([
                'title' => 'Sample Template',
                'body' => 'Hello $(first_name)',
                'footer' => 'Unsubscribe',
            ], 200),
        ]);

        $result = $this->client->preview('sample_template');

        $this->assertSame('Sample Template', $result['title']);
        $this->assertSame('Hello $(first_name)', $result['body']);
    }

    public function test_variables_calls_microservice(): void
    {
        Http::fake([
            '*variables/all' => Http::response([
                'custom' => [
                    ['name' => 'loyalty_points', 'type' => 'custom'],
                ],
                'builtin' => [
                    ['name' => 'first_name', 'type' => 'built-in'],
                ],
            ], 200),
        ]);

        $result = $this->client->allVariables();

        $this->assertCount(1, $result['custom']);
        $this->assertCount(1, $result['builtin']);
    }
}
