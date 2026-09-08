<?php

namespace Tests\Feature\Templates;

use App\Domains\Templates\Jobs\SubmitTemplateJob;
use App\Models\Template;
use App\Models\Variable;
use App\Models\WhatsappFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TemplateBuilderTest extends TestCase
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

    public function test_owner_can_start_template_builder_on_body_step(): void
    {
        $this->actingAsTenantUser()
            ->get(route('templates.builder.create'))
            ->assertRedirect();

        $template = Template::query()->first();
        $this->assertNotNull($template);
        $this->assertFalse($template->isSetupComplete());

        $this->actingAsTenantUser()
            ->get(route('templates.builder.body', $template))
            ->assertOk()
            ->assertSee('Template Name')
            ->assertSee('English (UK)', false);
    }

    public function test_owner_can_create_template_and_progress_wizard(): void
    {
        Variable::factory()->create([
            'name' => 'first_name',
            'whatsapp_line_id' => $this->testLine->id,
        ]);

        $this->actingAsTenantUser()
            ->get(route('templates.builder.create'));

        $template = Template::query()->first();
        $this->assertNotNull($template);

        $this->actingAsTenantUser()
            ->post(route('templates.builder.body.save', $template), [
                'name' => 'welcome_message',
                'category' => 'MARKETING',
                'language' => 'en_GB',
                'template_type' => 'regular',
                'body_text' => 'Hello $(first_name)',
            ])
            ->assertRedirect(route('templates.builder.header', $template));

        $this->actingAsTenantUser()
            ->post(route('templates.builder.header.save', $template), [
                'header_type' => 'text',
                'header_text' => 'Welcome',
            ])
            ->assertRedirect(route('templates.builder.footer', $template));

        $template->refresh();
        $this->assertSame('draft', $template->status->value);
        $this->assertSame('MARKETING', $template->category);
        $this->assertSame('en_GB', $template->language);
        $this->assertStringContainsString('first_name', (string) $template->body_preview);
        $this->assertTrue($template->variables()->where('name', 'first_name')->exists());
        $this->assertSame('welcome_message', $template->name);
        $this->assertTrue($template->isSetupComplete());
        $this->assertSame('regular', $template->wizardPayload()['meta']['template_type'] ?? null);
    }

    public function test_body_step_requires_setup_fields_before_proceeding(): void
    {
        $this->actingAsTenantUser()->get(route('templates.builder.create'));
        $template = Template::query()->firstOrFail();

        $this->actingAsTenantUser()
            ->post(route('templates.builder.body.save', $template), [
                'body_text' => 'Hello',
            ])
            ->assertSessionHasErrors(['name', 'category', 'language', 'template_type']);
    }

    public function test_duplicate_template_name_is_rejected_on_body_setup(): void
    {
        Template::factory()->draft()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'name' => 'sparshthakur',
            'code' => 'sparshthakur',
            'payload' => array_merge(Template::defaultPayload(), [
                'meta' => [
                    'name' => 'sparshthakur',
                    'category' => 'MARKETING',
                    'language' => 'en_GB',
                    'template_type' => 'regular',
                    'setup_completed' => true,
                ],
            ]),
        ]);

        $this->actingAsTenantUser()->get(route('templates.builder.create'));
        $draft = Template::query()
            ->where('id', '!=', Template::query()->where('code', 'sparshthakur')->value('id'))
            ->latest('id')
            ->first();

        $this->assertNotNull($draft);

        $this->actingAsTenantUser()
            ->post(route('templates.builder.body.save', $draft), [
                'name' => 'sparshthakur',
                'category' => 'MARKETING',
                'language' => 'en_GB',
                'template_type' => 'regular',
                'body_text' => 'Hello there',
            ])
            ->assertSessionHasErrors(['name']);
    }

    public function test_soft_deleted_template_name_can_be_reused(): void
    {
        $deleted = Template::factory()->draft()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'name' => 'reusable_name',
            'code' => 'reusable_name',
            'payload' => array_merge(Template::defaultPayload(), [
                'meta' => [
                    'name' => 'reusable_name',
                    'category' => 'MARKETING',
                    'language' => 'en_GB',
                    'template_type' => 'regular',
                    'setup_completed' => true,
                ],
            ]),
        ]);

        $deleted->delete();

        $this->actingAsTenantUser()->get(route('templates.builder.create'));
        $draft = Template::query()
            ->where('id', '!=', $deleted->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($draft);

        $this->actingAsTenantUser()
            ->post(route('templates.builder.body.save', $draft), [
                'name' => 'reusable_name',
                'category' => 'MARKETING',
                'language' => 'en_GB',
                'template_type' => 'regular',
                'body_text' => 'Hello again',
            ])
            ->assertRedirect(route('templates.builder.header', $draft));

        $draft->refresh();
        $this->assertSame('reusable_name', $draft->name);
        $this->assertSame('reusable_name', $draft->code);
    }

    public function test_header_requires_content_when_type_is_not_none(): void
    {
        $template = Template::factory()->draft()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'name' => 'header_test',
            'category' => 'MARKETING',
            'language' => 'en_GB',
            'payload' => array_merge(Template::defaultPayload(), [
                'meta' => [
                    'name' => 'header_test',
                    'category' => 'MARKETING',
                    'language' => 'en_GB',
                    'template_type' => 'regular',
                    'setup_completed' => true,
                ],
            ]),
        ]);

        $this->actingAsTenantUser()
            ->post(route('templates.builder.header.save', $template), [
                'header_type' => 'text',
                'header_text' => '',
            ])
            ->assertSessionHasErrors(['header_text']);
    }

    public function test_owner_can_skip_optional_header_footer_and_buttons(): void
    {
        $template = Template::factory()->draft()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'name' => 'optional_steps',
            'category' => 'MARKETING',
            'language' => 'en_GB',
            'payload' => array_merge(Template::defaultPayload(), [
                'meta' => [
                    'name' => 'optional_steps',
                    'category' => 'MARKETING',
                    'language' => 'en_GB',
                    'template_type' => 'regular',
                    'setup_completed' => true,
                ],
                'body' => ['text' => 'Hello world', 'samples' => []],
            ]),
            'body_preview' => 'Hello world',
        ]);

        $this->actingAsTenantUser()
            ->post(route('templates.builder.header.save', $template), [
                'header_type' => 'none',
            ])
            ->assertRedirect(route('templates.builder.footer', $template));

        $this->actingAsTenantUser()
            ->post(route('templates.builder.footer.save', $template), [
                'footer_text' => '',
            ])
            ->assertRedirect(route('templates.builder.buttons', $template));

        $this->actingAsTenantUser()
            ->post(route('templates.builder.buttons.save', $template), [
                'button_mode' => 'none',
            ])
            ->assertRedirect(route('templates.builder.submit', $template));

        $template->refresh();
        $payload = $template->wizardPayload();
        $this->assertSame('none', $payload['header']['type']);
        $this->assertSame('', $payload['footer']['text']);
        $this->assertSame([], $payload['buttons']);
        $this->assertSame('none', $payload['button_mode']);
    }

    public function test_owner_can_submit_template_for_review(): void
    {
        Bus::fake();

        $template = Template::factory()->draft()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'payload' => array_merge(Template::defaultPayload(), [
                'meta' => [
                    'name' => 'submit_test',
                    'category' => 'MARKETING',
                    'language' => 'en_GB',
                    'template_type' => 'regular',
                    'setup_completed' => true,
                ],
            ]),
        ]);

        $this->actingAsTenantUser()
            ->post(route('templates.builder.submit.save', $template), [
                'confirm' => '1',
            ])
            ->assertRedirect(route('templates.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('templates', [
            'id' => $template->id,
            'status' => 'pending_review',
        ]);

        Bus::assertDispatched(SubmitTemplateJob::class, fn (SubmitTemplateJob $job) => $job->templateId === $template->id);
    }

    public function test_whatsapp_flow_button_resolves_meta_flow_id_and_screen(): void
    {
        $flow = WhatsappFlow::factory()->active()->create([
            'meta_flow_id' => 'flow_template_btn',
            'meta_json' => [
                'screens' => [
                    ['id' => 'SCREEN_A'],
                ],
            ],
        ]);

        $template = Template::factory()->draft()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'payload' => array_merge(Template::defaultPayload(), [
                'meta' => [
                    'name' => 'flow_button_test',
                    'category' => 'MARKETING',
                    'language' => 'en_GB',
                    'template_type' => 'regular',
                    'setup_completed' => true,
                ],
                'body' => ['text' => 'Tap to start', 'samples' => []],
            ]),
            'body_preview' => 'Tap to start',
        ]);

        $this->actingAsTenantUser()
            ->post(route('templates.builder.buttons.save', $template), [
                'button_mode' => 'whatsapp_flows',
                'buttons' => [
                    [
                        'type' => 'flow',
                        'text' => 'Open Flow',
                        'flow_id' => (string) $flow->id,
                    ],
                ],
            ])
            ->assertRedirect(route('templates.builder.submit', $template));

        $template->refresh();
        $button = $template->wizardPayload()['buttons'][0] ?? [];

        $this->assertSame('flow', $button['type']);
        $this->assertSame('flow_template_btn', $button['flow_id']);
        $this->assertSame('SCREEN_A', $button['navigate_screen']);
    }
}
