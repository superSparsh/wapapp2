<?php

namespace Tests\Feature\FormBuilder;

use App\Domains\FormBuilder\Enums\FormStatus;
use App\Models\MailList;
use App\Models\SignupForm;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_owner_can_view_create_form_page(): void
    {
        $this->actingAsTenantUser()
            ->get(route('form-builder.create'))
            ->assertOk()
            ->assertViewIs('form-builder.create')
            ->assertViewHas('mailLists')
            ->assertViewHas('defaultFields');
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
