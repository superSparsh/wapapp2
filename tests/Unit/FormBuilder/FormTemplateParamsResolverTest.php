<?php

declare(strict_types=1);

namespace Tests\Unit\FormBuilder;

use App\Domains\FormBuilder\Services\FormTemplateParamsResolver;
use App\Models\Contact;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class FormTemplateParamsResolverTest extends TestCase
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

    public function test_auto_fills_template_placeholders_including_unsub(): void
    {
        $template = Template::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'body_preview' => 'Hi $(first_name), reply $(unsub) to leave.',
            'payload' => [
                'body' => ['text' => 'Hi $(first_name), reply $(unsub) to leave.'],
            ],
        ]);

        $contact = Contact::factory()->create([
            'phone' => '919876543210',
            'name' => 'Sparsh Thakur',
            'custom_fields' => [
                'FIRST_NAME' => 'Sparsh',
                'LAST_NAME' => 'Thakur',
            ],
        ]);

        $params = app(FormTemplateParamsResolver::class)->forSubmission(
            $template,
            [
                'phone' => '919876543210',
                'first_name' => 'Sparsh',
                'last_name' => 'Thakur',
            ],
            $contact,
            '919876543210',
        );

        $this->assertSame('Sparsh', $params['first_name'] ?? null);
        $this->assertSame((string) $contact->id, $params['unsub'] ?? null);
        $this->assertArrayNotHasKey('phone', $params);
    }

    public function test_falls_back_when_submission_value_missing(): void
    {
        $template = Template::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'body_preview' => 'Hello $(full_name) / $(phone)',
            'payload' => [
                'body' => ['text' => 'Hello $(full_name) / $(phone)'],
            ],
        ]);

        $params = app(FormTemplateParamsResolver::class)->forSubmission(
            $template,
            [],
            null,
            '919811122233',
        );

        $this->assertSame('919811122233', $params['full_name'] ?? null);
        $this->assertSame('919811122233', $params['phone'] ?? null);
    }
}
