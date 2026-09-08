<?php

declare(strict_types=1);

namespace Tests\Feature\Campaigns;

use App\Domains\Templates\Enums\TemplateStatus;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\MailList;
use App\Models\Template;
use App\Models\WhatsappLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CampaignWizardTest extends TestCase
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

    // ─── Step Rendering ──────────────────────────────────────────────────

    public function test_step_1_renders(): void
    {
        $this->actingAsTenantUser()
            ->get(route('campaigns.create.step', 1))
            ->assertOk()
            ->assertViewIs('campaigns.create.step-1');
    }

    public function test_step_2_renders(): void
    {
        MailList::factory()->create();

        $this->actingAsTenantUser()
            ->get(route('campaigns.create.step', 2))
            ->assertOk()
            ->assertViewIs('campaigns.create.step-2');
    }

    public function test_step_3_renders(): void
    {
        $this->actingAsTenantUser()
            ->get(route('campaigns.create.step', 3))
            ->assertOk()
            ->assertViewIs('campaigns.create.step-3');
    }

    public function test_step_4_renders(): void
    {
        $this->actingAsTenantUser()
            ->get(route('campaigns.create.step', 4))
            ->assertOk()
            ->assertViewIs('campaigns.create.step-4');
    }

    public function test_step_5_redirects_to_schedule_confirm(): void
    {
        $this->actingAsTenantUser()
            ->get(route('campaigns.create.step', 5))
            ->assertRedirect(route('campaigns.create.step', 6));
    }

    public function test_step_6_renders(): void
    {
        $this->actingAsTenantUser()
            ->get(route('campaigns.create.step', 6))
            ->assertOk()
            ->assertViewIs('campaigns.create.step-6')
            ->assertSee('Send a test WhatsApp message');
    }

    public function test_invalid_step_returns_404(): void
    {
        $this->actingAsTenantUser()
            ->get(route('campaigns.create.step', 7))
            ->assertNotFound();
    }

    // ─── Step Saving ─────────────────────────────────────────────────────

    public function test_save_step_1_stores_name_in_session(): void
    {
        $this->actingAsTenantUser()
            ->post(route('campaigns.create.save', 1), [
                'name' => 'Test Campaign',
                'whatsapp_line_id' => $this->testLine->id,
            ])
            ->assertRedirect(route('campaigns.create.step', 2));

        $this->withSession(['campaign_wizard' => session('campaign_wizard')])
            ->get(route('campaigns.create.step', 2))
            ->assertOk();
    }

    public function test_save_step_2_stores_audience(): void
    {
        $audience = MailList::factory()->create();

        $this->actingAsTenantUser()
            ->post(route('campaigns.create.save', 2), [
                'audience_id' => $audience->id,
            ])
            ->assertRedirect(route('campaigns.create.step', 3));
    }

    public function test_save_step_3_with_variables_goes_to_variables_step(): void
    {
        $payload = Template::defaultPayload();
        $payload['body']['text'] = 'Hello $(first_name)';

        $template = Template::factory()->create([
            'payload' => $payload,
            'body_preview' => 'Hello $(first_name)',
        ]);

        $this->actingAsTenantUser()
            ->post(route('campaigns.create.save', 3), [
                'template_id' => $template->id,
            ])
            ->assertRedirect(route('campaigns.create.step', 4));
    }

    public function test_save_step_3_without_variables_skips_to_schedule(): void
    {
        $payload = Template::defaultPayload();
        $payload['body']['text'] = 'Hello there, no placeholders.';
        $payload['header']['text'] = '';
        $payload['footer']['text'] = '';

        $template = Template::factory()->create([
            'payload' => $payload,
            'body_preview' => 'Hello there, no placeholders.',
        ]);

        $this->actingAsTenantUser()
            ->post(route('campaigns.create.save', 3), [
                'template_id' => $template->id,
            ])
            ->assertRedirect(route('campaigns.create.step', 6));
    }

    public function test_save_step_4_persists_recipient_grid_and_goes_to_schedule(): void
    {
        $audience = MailList::factory()->create();
        $contact = \App\Models\Contact::factory()->create([
            'mail_list_id' => $audience->id,
            'name' => 'Ada Lovelace',
            'phone' => '919876543210',
            'status' => \App\Domains\Audience\Enums\ContactStatus::Subscribed,
        ]);

        $payload = Template::defaultPayload();
        $payload['body']['text'] = 'Hi $(first_name) from $(company)';

        $template = Template::factory()->create([
            'payload' => $payload,
            'body_preview' => 'Hi $(first_name) from $(company)',
        ]);

        $this->actingAsTenantUser()
            ->post(route('campaigns.create.save', 1), [
                'name' => 'Vars Grid',
                'whatsapp_line_id' => $this->testLine->id,
            ]);

        $this->actingAsTenantUser()
            ->post(route('campaigns.create.save', 2), [
                'audience_id' => $audience->id,
            ]);

        $this->actingAsTenantUser()
            ->post(route('campaigns.create.save', 3), [
                'template_id' => $template->id,
            ])
            ->assertRedirect(route('campaigns.create.step', 4));

        $draftId = (int) session('campaign_wizard.draft_id');
        $recipient = \App\Models\CampaignRecipient::query()
            ->where('campaign_id', $draftId)
            ->where('contact_phone', $contact->phone)
            ->first();
        $this->assertNotNull($recipient);

        $this->actingAsTenantUser()
            ->post(route('campaigns.create.save', 4), [
                'recipients' => [
                    $recipient->id => [
                        'recipient_id' => $recipient->id,
                        'values' => [
                            'first_name' => 'Ada',
                            'company' => 'Acme',
                        ],
                    ],
                ],
            ])
            ->assertRedirect(route('campaigns.create.step', 6));

        $this->assertSame('Acme', $recipient->fresh()->variable_values['company'] ?? null);
    }

    public function test_step_3_template_options_use_contain_variables_pill_not_language(): void
    {
        $payload = Template::defaultPayload();
        $payload['body']['text'] = 'Hello $(first_name)';

        Template::factory()->create([
            'name' => 'Var Template',
            'language' => 'en_GB',
            'category' => 'UTILITY',
            'payload' => $payload,
            'body_preview' => 'Hello $(first_name)',
        ]);

        Template::factory()->create([
            'name' => 'Plain Template',
            'language' => 'en',
            'category' => 'MARKETING',
            'payload' => array_merge(Template::defaultPayload(), [
                'body' => ['text' => 'No placeholders here', 'samples' => []],
            ]),
            'body_preview' => 'No placeholders here',
        ]);

        $this->actingAsTenantUser()
            ->get(route('campaigns.create.step', 3))
            ->assertOk()
            ->assertSee('Var Template')
            ->assertSee('data-pill="contain variables"', false)
            ->assertSee('data-category-pill="Utility"', false)
            ->assertSee('data-category-pill="Marketing"', false)
            ->assertSee('data-show-pricing="1"', false)
            ->assertSee('Plain Template')
            ->assertDontSee('en_GB')
            ->assertDontSee('(en)');
    }

    public function test_template_preview_endpoint_returns_substituted_body(): void
    {
        $payload = Template::defaultPayload();
        $payload['body']['text'] = 'Hello $(first_name), offer $(discount)';
        $payload['body']['samples'] = ['Sam', '20%'];

        $template = Template::factory()->create([
            'payload' => $payload,
            'body_preview' => 'Hello $(first_name), offer $(discount)',
        ]);

        $this->actingAsTenantUser()
            ->getJson(route('campaigns.create.template-preview', $template->id))
            ->assertOk()
            ->assertJsonPath('body', 'Hello $(first_name), offer $(discount)')
            ->assertJsonPath('variables.0.name', 'first_name')
            ->assertJsonPath('variables.1.name', 'discount');
    }

    public function test_step_4_lists_variables_from_template_body_without_pivot(): void
    {
        $audience = MailList::factory()->create();
        $contact = \App\Models\Contact::factory()->create([
            'mail_list_id' => $audience->id,
            'name' => 'Ada Lovelace',
            'phone' => '919876543299',
            'status' => \App\Domains\Audience\Enums\ContactStatus::Subscribed,
        ]);

        $payload = Template::defaultPayload();
        $payload['body']['text'] = 'Hi $(first_name), welcome to $(company)';

        $template = Template::factory()->create([
            'payload' => $payload,
            'body_preview' => 'Hi $(first_name), welcome to $(company)',
        ]);

        $this->actingAsTenantUser()
            ->post(route('campaigns.create.save', 1), [
                'name' => 'Var Campaign',
                'whatsapp_line_id' => $this->testLine->id,
            ]);
        $this->actingAsTenantUser()
            ->post(route('campaigns.create.save', 2), [
                'audience_id' => $audience->id,
            ]);
        $this->actingAsTenantUser()
            ->post(route('campaigns.create.save', 3), [
                'template_id' => $template->id,
            ]);

        $recipient = \App\Models\CampaignRecipient::query()
            ->where('contact_phone', $contact->phone)
            ->first();

        $this->actingAsTenantUser()
            ->get(route('campaigns.create.step', 4))
            ->assertOk()
            ->assertSee('$(first_name)', false)
            ->assertSee('$(company)', false)
            ->assertDontSee('$(unsub)', false)
            ->assertSee('Import Data')
            ->assertSee('name="recipients['.$recipient->id.'][values][first_name]"', false)
            ->assertSee('name="recipients['.$recipient->id.'][values][company]"', false)
            ->assertSee('Ada', false)
            ->assertSee('Same value for all');
    }

    public function test_save_step_6_redirects_to_store(): void
    {
        $this->actingAsTenantUser()
            ->post(route('campaigns.create.save', 6), [
                'send_mode' => 'now',
            ])
            ->assertRedirect(route('campaigns.store'));
    }

    public function test_wizard_session_persists_across_steps(): void
    {
        $response = $this->actingAsTenantUser()
            ->post(route('campaigns.create.save', 1), [
                'name' => 'Wizard Test',
                'whatsapp_line_id' => $this->testLine->id,
            ]);

        $response->assertRedirect(route('campaigns.create.step', 2));

        $audience = MailList::factory()->create();
        $response = $this->actingAsTenantUser()
            ->post(route('campaigns.create.save', 2), [
                'audience_id' => $audience->id,
            ]);

        $response->assertRedirect(route('campaigns.create.step', 3));

        $wizardData = session('campaign_wizard');
        $this->assertNotNull($wizardData);
    }

    public function test_full_wizard_flow_creates_campaign(): void
    {
        $audience = MailList::factory()->create();

        $this->actingAsTenantUser()
            ->post(route('campaigns.create.save', 1), [
                'name' => 'Full Wizard Campaign',
                'whatsapp_line_id' => $this->testLine->id,
            ]);

        $this->actingAsTenantUser()
            ->post(route('campaigns.create.save', 2), [
                'audience_id' => $audience->id,
            ]);

        $payload = Template::defaultPayload();
        $payload['body']['text'] = 'Static body without placeholders';
        $payload['header']['text'] = '';
        $payload['footer']['text'] = '';

        $template = Template::factory()->create([
            'payload' => $payload,
            'body_preview' => 'Static body without placeholders',
        ]);

        $this->actingAsTenantUser()
            ->post(route('campaigns.create.save', 3), [
                'template_id' => $template->id,
            ])
            ->assertRedirect(route('campaigns.create.step', 6));

        $this->actingAsTenantUser()
            ->post(route('campaigns.create.save', 6), [
                'send_mode' => 'now',
            ])
            ->assertRedirect(route('campaigns.store'));
    }

    public function test_step_1_shows_from_number_label_and_choose_placeholder(): void
    {
        $this->actingAsTenantUser()
            ->get(route('campaigns.create.step', 1))
            ->assertOk()
            ->assertSee('From Number')
            ->assertSee('Choose')
            ->assertDontSee('Info & Recipients*')
            ->assertDontSee('-- Select WhatsApp Line --');
    }

    public function test_wizard_test_message_requires_line_and_template(): void
    {
        $this->actingAsTenantUser()
            ->postJson(route('campaigns.create.test-message'), [
                'phone' => '919999999999',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonFragment(['message' => 'Please select a From Number in Info & Recipients.']);
    }

    public function test_wizard_test_message_sends_and_reports_success(): void
    {
        config([
            'whatsapp.alibaba.access_key_id' => null,
            'whatsapp.alibaba.access_key_secret' => null,
        ]);

        $line = WhatsappLine::factory()->create();
        $template = Template::factory()->create([
            'code' => 'TEST_TEMPLATE_CODE',
            'language' => 'en',
        ]);

        $this->withSession([
            'campaign_wizard' => [
                'name' => 'Test Campaign',
                'whatsapp_line_id' => $line->id,
                'template_id' => $template->id,
            ],
        ])
            ->actingAsTenantUser()
            ->postJson(route('campaigns.create.test-message'), [
                'phone' => '919999999999',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['message' => 'Test WhatsApp message sent successfully to 919999999999.']);
    }

    public function test_wizard_test_message_reports_provider_failure(): void
    {
        config([
            'whatsapp.alibaba.access_key_id' => 'test-key',
            'whatsapp.alibaba.access_key_secret' => 'test-secret',
        ]);

        $this->mock(\App\Domains\WhatsApp\Services\AlibabaCamsClient::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('sendChatappMessage')->andReturn(
                new \Illuminate\Http\Client\Response(
                    new \GuzzleHttp\Psr7\Response(400, [], json_encode([
                        'Code' => 'InvalidTemplate',
                        'Message' => 'Template code is invalid',
                    ]))
                )
            );
        });

        $line = WhatsappLine::factory()->create([
            'alibaba_cust_space_id' => 'SP123',
        ]);
        $template = Template::factory()->create([
            'code' => 'BAD_CODE',
            'language' => 'en',
        ]);

        $this->withSession([
            'campaign_wizard' => [
                'name' => 'Test Campaign',
                'whatsapp_line_id' => $line->id,
                'template_id' => $template->id,
            ],
        ])
            ->actingAsTenantUser()
            ->postJson(route('campaigns.create.test-message'), [
                'phone' => '919999999999',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonFragment(['success' => false])
            ->assertSee('Template code is invalid (InvalidTemplate)', false)
            ->assertSee('debug TemplateCode=BAD_CODE', false)
            ->assertSee('Language=en_GB', false);
    }

    public function test_create_campaign_start_clears_wizard_and_saves_leftover_as_draft(): void
    {
        $this->withSession([
            'campaign_wizard' => [
                'name' => 'Abandoned Campaign',
                'whatsapp_line_id' => $this->testLine->id,
            ],
        ])
            ->actingAsTenantUser()
            ->get(route('campaigns.create.start'))
            ->assertRedirect(route('campaigns.create.step', 1));

        $this->assertNull(session('campaign_wizard'));
        $this->assertDatabaseHas('campaigns', [
            'name' => 'Abandoned Campaign',
            'status' => CampaignStatus::Draft->value,
        ]);

        $this->actingAsTenantUser()
            ->get(route('campaigns.create.step', 1))
            ->assertOk()
            ->assertDontSee('Abandoned Campaign')
            ->assertSee('New Campaign');
    }

    public function test_create_campaign_start_with_empty_session_does_not_create_campaign(): void
    {
        $this->actingAsTenantUser()
            ->get(route('campaigns.create.start'))
            ->assertRedirect(route('campaigns.create.step', 1));

        $this->assertSame(0, Campaign::query()->count());
    }

    public function test_cancel_saves_progress_as_draft(): void
    {
        $this->withSession([
            'campaign_wizard' => [
                'name' => 'Halfway Campaign',
                'whatsapp_line_id' => $this->testLine->id,
            ],
        ])
            ->actingAsTenantUser()
            ->get(route('campaigns.create.cancel'))
            ->assertRedirect(route('campaigns.index'))
            ->assertSessionHas('status', 'Campaign saved as draft.');

        $this->assertNull(session('campaign_wizard'));
        $this->assertDatabaseHas('campaigns', [
            'name' => 'Halfway Campaign',
            'status' => CampaignStatus::Draft->value,
        ]);
    }

    public function test_save_step_1_creates_draft_campaign(): void
    {
        $this->actingAsTenantUser()
            ->post(route('campaigns.create.save', 1), [
                'name' => 'Draft From Step One',
                'whatsapp_line_id' => $this->testLine->id,
            ])
            ->assertRedirect(route('campaigns.create.step', 2));

        $this->assertDatabaseHas('campaigns', [
            'name' => 'Draft From Step One',
            'status' => CampaignStatus::Draft->value,
            'whatsapp_line_id' => $this->testLine->id,
        ]);
        $this->assertNotEmpty(session('campaign_wizard.draft_id'));
    }

    public function test_step_3_lists_only_approved_templates_with_code(): void
    {
        Template::factory()->create(['name' => 'Ready To Send']);
        Template::factory()->draft()->create(['name' => 'Local Draft Template']);
        Template::factory()->create([
            'name' => 'Pending Template',
            'status' => TemplateStatus::PendingReview,
            'code' => 'pending_code',
        ]);
        Template::factory()->create([
            'name' => 'Rejected Template',
            'status' => TemplateStatus::Rejected,
            'code' => 'rejected_code',
        ]);
        Template::factory()->create([
            'name' => 'Approved Without Code',
            'status' => TemplateStatus::Approved,
            'code' => '',
        ]);

        $this->actingAsTenantUser()
            ->get(route('campaigns.create.step', 3))
            ->assertOk()
            ->assertSee('Ready To Send')
            ->assertDontSee('Local Draft Template')
            ->assertDontSee('Pending Template')
            ->assertDontSee('Rejected Template')
            ->assertDontSee('Approved Without Code');
    }

    public function test_store_reuses_wizard_draft_instead_of_creating_another(): void
    {
        $audience = MailList::factory()->create();
        $template = Template::factory()->create();

        $this->actingAsTenantUser()
            ->post(route('campaigns.create.save', 1), [
                'name' => 'Single Campaign',
                'whatsapp_line_id' => $this->testLine->id,
            ]);

        $draftId = (int) session('campaign_wizard.draft_id');
        $this->assertGreaterThan(0, $draftId);

        $this->actingAsTenantUser()
            ->post(route('campaigns.store'), [
                'name' => 'Single Campaign',
                'whatsapp_line_id' => $this->testLine->id,
                'audience_id' => $audience->id,
                'template_id' => $template->id,
                'send_mode' => 'schedule',
                'scheduled_at' => now()->addDay()->toDateTimeString(),
            ])
            ->assertRedirect();

        $this->assertSame(1, Campaign::query()->where('name', 'Single Campaign')->count());
        $this->assertSame($draftId, Campaign::query()->where('name', 'Single Campaign')->value('id'));
        $this->assertSame(CampaignStatus::Scheduled, Campaign::query()->find($draftId)?->status);
    }
}
