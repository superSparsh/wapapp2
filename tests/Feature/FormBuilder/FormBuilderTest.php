<?php

namespace Tests\Feature\FormBuilder;

use App\Domains\FormBuilder\Enums\FormStatus;
use App\Models\FormSubmission;
use App\Models\MailList;
use App\Models\SignupForm;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class FormBuilderTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected MailList $mailList;
    protected Template $template;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();

        // Create shared MailList and Template for tests that need them
        $this->mailList = MailList::factory()->create();
        $this->template = Template::factory()->create();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_owner_can_view_form_builder_index(): void
    {
        SignupForm::factory()->create(['whatsapp_line_id' => $this->testLine->id]);

        $this->actingAsTenantUser()
            ->get(route('form-builder.index'))
            ->assertOk()
            ->assertViewIs('form-builder.index');
    }

    public function test_owner_can_upload_logo_and_preview_via_tenant_route(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('logo.png', 120, 80);

        $upload = $this->actingAsTenantUser()
            ->postJson(route('form-builder.upload-logo'), [
                'logo' => $file,
            ])
            ->assertOk()
            ->assertJsonStructure(['path', 'url']);

        $path = $upload->json('path');
        $url = $upload->json('url');

        $this->assertNotEmpty($path);
        $this->assertStringContainsString('/form-builder/logos/', (string) $url);
        Storage::disk('public')->assertExists($path);

        $this->actingAsTenantUser()
            ->get(route('form-builder.logos.show', ['path' => $path]))
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
    }

    public function test_owner_can_view_create_form_page(): void
    {
        $this->actingAsTenantUser()
            ->get(route('form-builder.create'))
            ->assertOk()
            ->assertViewIs('form-builder.create')
            ->assertViewHas('mailLists')
            ->assertViewHas('defaultFields');
    }

    public function test_create_page_lists_latest_mail_lists_and_approved_templates_first(): void
    {
        $olderList = MailList::factory()->create([
            'name' => 'Older List',
            'created_at' => now()->subDays(2),
        ]);
        $newerList = MailList::factory()->create([
            'name' => 'Newer List',
            'created_at' => now()->subHour(),
        ]);

        $olderTemplate = Template::factory()->create([
            'name' => 'Older Template',
            'created_at' => now()->subDays(2),
        ]);
        $newerTemplate = Template::factory()->create([
            'name' => 'Newer Template',
            'created_at' => now()->subHour(),
        ]);

        $response = $this->actingAsTenantUser()
            ->get(route('form-builder.create'))
            ->assertOk();

        $mailListIds = array_keys($response->viewData('mailLists')->all());
        $templateIds = array_keys($response->viewData('templates')->all());

        $newerListPos = array_search($newerList->id, $mailListIds, true);
        $olderListPos = array_search($olderList->id, $mailListIds, true);
        $this->assertNotFalse($newerListPos);
        $this->assertNotFalse($olderListPos);
        $this->assertLessThan($olderListPos, $newerListPos);

        $newerTemplatePos = array_search($newerTemplate->id, $templateIds, true);
        $olderTemplatePos = array_search($olderTemplate->id, $templateIds, true);
        $this->assertNotFalse($newerTemplatePos);
        $this->assertNotFalse($olderTemplatePos);
        $this->assertLessThan($olderTemplatePos, $newerTemplatePos);
    }

    public function test_owner_can_create_a_new_form(): void
    {
        $response = $this->actingAsTenantUser()
            ->post(route('form-builder.store'), [
                'name' => 'Test Signup Form',
                'list_id' => $this->mailList->id,
                'template_id' => $this->template->id,
                'fields' => json_encode([
                    ['type' => 'header', 'label' => 'Header', 'placeholder' => 'Welcome'],
                    ['type' => 'phone', 'label' => 'WhatsApp Number', 'required' => true],
                ]),
                'activate' => true,
            ]);

        $form = SignupForm::query()->first();
        $this->assertNotNull($form);
        $this->assertSame('Test Signup Form', $form->name);
        $this->assertSame($this->mailList->id, $form->list_id);
        $this->assertSame($this->template->id, $form->template_id);
        $this->assertTrue($form->isActive());
        $this->assertNotEmpty($form->slug);
        $this->assertNotEmpty($form->embed_code);
    }

    public function test_create_form_requires_list_id(): void
    {
        $this->actingAsTenantUser()
            ->post(route('form-builder.store'), [
                'name' => 'No List Form',
                'template_id' => $this->template->id,
            ])
            ->assertSessionHasErrors('list_id');
    }

    public function test_create_form_requires_template_id(): void
    {
        $this->actingAsTenantUser()
            ->post(route('form-builder.store'), [
                'name' => 'No Template Form',
                'list_id' => $this->mailList->id,
            ])
            ->assertSessionHasErrors('template_id');
    }

    public function test_owner_can_view_edit_form_page(): void
    {
        $form = SignupForm::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'list_id' => $this->mailList->id,
            'template_id' => $this->template->id,
        ]);

        $this->actingAsTenantUser()
            ->get(route('form-builder.edit', $form))
            ->assertOk()
            ->assertViewIs('form-builder.edit')
            ->assertViewHas('form')
            ->assertViewHas('mailLists');
    }

    public function test_owner_can_update_a_form(): void
    {
        $form = SignupForm::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'list_id' => $this->mailList->id,
            'template_id' => $this->template->id,
            'name' => 'Original Name',
        ]);

        $this->actingAsTenantUser()
            ->put(route('form-builder.update', $form), [
                'name' => 'Updated Name',
                'list_id' => $this->mailList->id,
                'template_id' => $this->template->id,
                'fields' => json_encode([
                    ['type' => 'header', 'label' => 'Header', 'placeholder' => 'Updated Welcome'],
                ]),
                'activate' => true,
            ])
            ->assertRedirect(route('form-builder.edit', $form));

        $form->refresh();
        $this->assertSame('Updated Name', $form->name);
        $this->assertTrue($form->isActive());
    }

    public function test_owner_can_delete_a_form(): void
    {
        $form = SignupForm::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
        ]);

        $this->actingAsTenantUser()
            ->delete(route('form-builder.destroy', $form))
            ->assertRedirect(route('form-builder.index'));

        $this->assertSoftDeleted('signup_forms', ['id' => $form->id]);
    }

    public function test_owner_can_toggle_form_status(): void
    {
        $form = SignupForm::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'status' => FormStatus::Active,
        ]);

        $this->actingAsTenantUser()
            ->post(route('form-builder.toggle', $form))
            ->assertRedirect();

        $form->refresh();
        $this->assertFalse($form->isActive());
    }

    public function test_owner_can_toggle_form_status_via_ajax_without_redirect(): void
    {
        $form = SignupForm::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'status' => FormStatus::Active,
        ]);

        $this->actingAsTenantUser()
            ->postJson(route('form-builder.toggle', $form))
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'is_active' => false,
                'status_label' => 'Inactive',
            ]);

        $form->refresh();
        $this->assertFalse($form->isActive());
    }

    public function test_index_shows_empty_state_when_no_forms(): void
    {
        $this->actingAsTenantUser()
            ->get(route('form-builder.index'))
            ->assertOk()
            ->assertSee('No forms yet');
    }

    public function test_index_shows_forms_with_statistics(): void
    {
        $form = SignupForm::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'name' => 'Newsletter Signup',
            'submission_count' => 42,
            'sent_count' => 40,
        ]);

        $this->actingAsTenantUser()
            ->get(route('form-builder.index'))
            ->assertOk()
            ->assertSee('Newsletter Signup')
            ->assertSee('42');
    }

    public function test_default_fields_are_included_when_no_fields_provided(): void
    {
        $this->actingAsTenantUser()
            ->post(route('form-builder.store'), [
                'name' => 'Default Fields Form',
                'list_id' => $this->mailList->id,
                'template_id' => $this->template->id,
                'activate' => true,
            ]);

        $form = SignupForm::query()->first();
        $this->assertNotNull($form);
        $fields = $form->fields;
        $this->assertNotEmpty($fields);

        // Should have phone, first_name, last_name as defaults
        $types = array_column($fields, 'type');
        $this->assertContains('phone', $types);
        $this->assertContains('first_name', $types);
        $this->assertContains('last_name', $types);
    }

    public function test_owner_can_view_form_statistics_page(): void
    {
        $form = SignupForm::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'list_id' => $this->mailList->id,
            'template_id' => $this->template->id,
            'name' => 'Stats Form',
            'submission_count' => 10,
            'sent_count' => 8,
        ]);

        $this->actingAsTenantUser()
            ->get(route('form-builder.statistics', $form))
            ->assertOk()
            ->assertViewIs('form-builder.statistics')
            ->assertSee('Stats Form')
            ->assertSee('Total Submissions')
            ->assertSee('Recent submissions');
    }

    public function test_statistics_page_shows_failed_reason(): void
    {
        $form = SignupForm::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'list_id' => $this->mailList->id,
            'template_id' => $this->template->id,
            'name' => 'Failed Stats Form',
        ]);

        FormSubmission::query()->create([
            'signup_form_id' => $form->id,
            'phone' => '+919876543210',
            'submission_data' => ['phone' => '919876543210'],
            'message_status' => 'failed',
            'failed_reason' => 'Template not configured',
            'failed_at' => now(),
        ]);

        $this->actingAsTenantUser()
            ->get(route('form-builder.statistics', $form))
            ->assertOk()
            ->assertSee('Failure reason')
            ->assertSee('Template not configured');
    }

    public function test_statistics_detail_renders(): void
    {
        $form = SignupForm::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'list_id' => $this->mailList->id,
            'template_id' => $this->template->id,
            'name' => 'Detail Form',
        ]);

        FormSubmission::query()->create([
            'signup_form_id' => $form->id,
            'phone' => '+919876543210',
            'submission_data' => ['phone' => '919876543210'],
            'message_status' => 'sent',
            'sent_at' => now(),
        ]);

        $this->actingAsTenantUser()
            ->get(route('form-builder.statistics.detail', $form))
            ->assertOk()
            ->assertViewIs('form-builder.detail')
            ->assertViewHas('form')
            ->assertViewHas('submissions')
            ->assertSee('Detail Form')
            ->assertSee('Submissions')
            ->assertSee('+919876543210');
    }

    public function test_statistics_detail_filters_by_status(): void
    {
        $form = SignupForm::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'list_id' => $this->mailList->id,
            'template_id' => $this->template->id,
        ]);

        FormSubmission::query()->create([
            'signup_form_id' => $form->id,
            'phone' => '+911111111111',
            'submission_data' => [],
            'message_status' => 'delivered',
            'sent_at' => now(),
            'delivered_at' => now(),
        ]);
        FormSubmission::query()->create([
            'signup_form_id' => $form->id,
            'phone' => '+912222222222',
            'submission_data' => [],
            'message_status' => 'failed',
            'failed_reason' => 'Provider rejected',
            'failed_at' => now(),
        ]);
        FormSubmission::query()->create([
            'signup_form_id' => $form->id,
            'phone' => '+913333333333',
            'submission_data' => [],
            'message_status' => 'failed',
            'failed_reason' => 'Wallet empty',
            'failed_at' => now(),
        ]);

        $response = $this->actingAsTenantUser()
            ->get(route('form-builder.statistics.detail', ['form' => $form, 'status' => 'failed']))
            ->assertOk();

        $response->assertViewHas('submissions', function ($submissions) {
            return $submissions->total() === 2;
        });
        $response->assertSee('Provider rejected')
            ->assertSee('Wallet empty')
            ->assertDontSee('+911111111111');
    }

    public function test_statistics_detail_paginates(): void
    {
        $form = SignupForm::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'list_id' => $this->mailList->id,
            'template_id' => $this->template->id,
        ]);

        for ($i = 0; $i < 25; $i++) {
            FormSubmission::query()->create([
                'signup_form_id' => $form->id,
                'phone' => '+91987654'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'submission_data' => [],
                'message_status' => 'sent',
                'sent_at' => now(),
            ]);
        }

        $this->actingAsTenantUser()
            ->get(route('form-builder.statistics.detail', $form))
            ->assertOk()
            ->assertViewHas('submissions', function ($submissions) {
                return $submissions->total() === 25
                    && $submissions->perPage() === 10;
            });
    }

    public function test_statistics_export_downloads_csv(): void
    {
        $form = SignupForm::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'list_id' => $this->mailList->id,
            'template_id' => $this->template->id,
        ]);

        FormSubmission::query()->create([
            'signup_form_id' => $form->id,
            'phone' => '+919876543210',
            'submission_data' => [],
            'message_status' => 'failed',
            'failed_reason' => 'Template rejected',
            'failed_at' => now(),
        ]);

        $response = $this->actingAsTenantUser()
            ->get(route('form-builder.statistics.export', $form))
            ->assertOk();

        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('+919876543210', $response->streamedContent());
        $this->assertStringContainsString('Template rejected', $response->streamedContent());
    }

    public function test_edit_page_does_not_show_behavior_embed_or_statistics_sections(): void
    {
        $form = SignupForm::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'list_id' => $this->mailList->id,
            'template_id' => $this->template->id,
        ]);

        $this->actingAsTenantUser()
            ->get(route('form-builder.edit', $form))
            ->assertOk()
            ->assertDontSee('>Behavior</', false)
            ->assertDontSee('Embed Code', false)
            ->assertDontSee('>Statistics</', false)
            ->assertSee('Form Canvas');
    }

    public function test_owner_can_deactivate_form_via_update(): void
    {
        $form = SignupForm::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'list_id' => $this->mailList->id,
            'template_id' => $this->template->id,
            'status' => FormStatus::Active,
        ]);

        $this->actingAsTenantUser()
            ->put(route('form-builder.update', $form), [
                'name' => $form->name,
                'list_id' => $this->mailList->id,
                'template_id' => $this->template->id,
                'activate' => '0',
            ])
            ->assertRedirect(route('form-builder.edit', $form));

        $form->refresh();
        $this->assertFalse($form->isActive());
    }

    public function test_owner_can_save_legacy_pascal_case_field_types(): void
    {
        $form = SignupForm::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'list_id' => $this->mailList->id,
            'template_id' => $this->template->id,
            'fields' => [
                [
                    'id' => 'phone_number',
                    'type' => 'Input',
                    'label' => 'Whatsapp Number',
                    'name' => 'phone_number',
                    'required' => true,
                    'unremovable' => true,
                ],
                [
                    'id' => 'first_name',
                    'type' => 'Input',
                    'label' => 'First Name',
                    'name' => 'first_name',
                    'required' => true,
                ],
                [
                    'id' => 'city',
                    'type' => 'Select',
                    'label' => 'City',
                    'options' => ['Delhi', 'Mumbai'],
                ],
            ],
        ]);

        $this->actingAsTenantUser()
            ->put(route('form-builder.update', $form), [
                'name' => $form->name,
                'list_id' => $this->mailList->id,
                'template_id' => $this->template->id,
                'activate' => '1',
                'fields' => json_encode($form->fields),
            ])
            ->assertRedirect(route('form-builder.edit', $form))
            ->assertSessionHasNoErrors();

        $form->refresh();
        $types = array_column($form->fields, 'type');
        $this->assertContains('phone', $types);
        $this->assertContains('first_name', $types);
        $this->assertContains('dropdown', $types);
        $this->assertNotContains('Input', $types);
        $this->assertNotContains('Select', $types);
    }
}
