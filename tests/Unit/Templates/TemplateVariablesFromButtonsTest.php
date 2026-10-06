<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use App\Domains\Templates\Services\TemplatePreviewService;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TemplateVariablesFromButtonsTest extends TestCase
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

    public function test_extracts_unsub_placeholder_from_button_url(): void
    {
        $template = Template::factory()->create([
            'payload' => array_merge(Template::defaultPayload(), [
                'body' => ['text' => 'Hello $(name)'],
                'buttons' => [[
                    'text' => 'Stop promotions',
                    'type' => 'unsubscribe',
                    'url' => '/unsubscribe-list/$(unsub)',
                ]],
            ]),
        ]);

        $names = collect(app(TemplatePreviewService::class)->variablesForTemplate($template))
            ->pluck('name')
            ->all();

        $this->assertContains('name', $names);
        $this->assertContains('unsub', $names);
    }
}
