<?php

namespace Tests\Feature\FormBuilder;

use App\Models\Contact;
use App\Models\FormSubmission;
use App\Models\SignupForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class FormSubmissionTest extends TestCase
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

    /**
     * @return array{tenant: string, slug: string}
     */
    private function publicFormParams(string $slug): array
    {
        return [
            'tenant' => $this->testTenant->id,
            'slug' => $slug,
        ];
    }

    public function test_public_form_can_be_accessed_by_slug(): void
    {
        $form = SignupForm::factory()->active()->create([
            'whatsapp_line_id' => $this->testLine->id,
        ]);

        $this->get(route('public.form.show', $this->publicFormParams($form->slug)))
            ->assertOk()
            ->assertViewIs('form-builder.public')
            ->assertSee('Powered by WapApp')
            ->assertDontSee('Your details are used to connect with you on WhatsApp.');
    }

    public function test_inactive_form_returns_404(): void
    {
        $form = SignupForm::factory()->inactive()->create([
            'whatsapp_line_id' => $this->testLine->id,
        ]);

        $this->get(route('public.form.show', $this->publicFormParams($form->slug)))
            ->assertNotFound();
    }

    public function test_public_form_submission_creates_contact_and_submission_record(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $form = SignupForm::factory()->active()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'template_id' => null,
        ]);

        $this->post(route('public.form.submit', $this->publicFormParams($form->slug)), [
            'phone' => '919876543210',
            'input' => 'John Doe',
        ])
            ->assertRedirect();

        // Contact created
        $contact = Contact::query()->where('phone', '+919876543210')->first();
        $this->assertNotNull($contact);

        // Submission record created
        $submission = FormSubmission::query()->first();
        $this->assertNotNull($submission);
        $this->assertSame($form->id, $submission->signup_form_id);
        $this->assertSame('+919876543210', $submission->phone);

        // Cached count incremented
        $form->refresh();
        $this->assertSame(1, $form->submission_count);
    }

    public function test_public_form_submission_validates_phone_required(): void
    {
        $form = SignupForm::factory()->active()->create([
            'whatsapp_line_id' => $this->testLine->id,
        ]);

        $this->post(route('public.form.submit', $this->publicFormParams($form->slug)), [
            'input' => 'John Doe',
        ])
            ->assertSessionHasErrors(['phone']);
    }

    public function test_public_form_submission_with_redirect_url(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $form = SignupForm::factory()->active()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'redirect_url' => 'https://example.com/thank-you',
        ]);

        $this->post(route('public.form.submit', $this->publicFormParams($form->slug)), [
            'phone' => '919876543210',
        ])
            ->assertRedirect('https://example.com/thank-you');
    }

    public function test_public_form_renders_default_name_fields(): void
    {
        $form = SignupForm::factory()->active()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'fields' => [
                ['type' => 'header', 'label' => 'Header', 'placeholder' => 'Join us', 'required' => true],
                ['type' => 'phone', 'label' => 'WhatsApp Number', 'required' => true, 'placeholder' => 'Enter number'],
                ['type' => 'first_name', 'label' => 'First Name', 'required' => true, 'placeholder' => 'First'],
                ['type' => 'last_name', 'label' => 'Last Name', 'required' => true, 'placeholder' => 'Last'],
            ],
        ]);

        $this->get(route('public.form.show', $this->publicFormParams($form->slug)))
            ->assertOk()
            ->assertSee('Join us')
            ->assertSee('name="first_name"', false)
            ->assertSee('name="last_name"', false)
            ->assertSee('name="phone"', false);
    }

    public function test_public_form_submission_accepts_first_and_last_name(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $form = SignupForm::factory()->active()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'template_id' => null,
        ]);

        $this->post(route('public.form.submit', $this->publicFormParams($form->slug)), [
            'phone' => '919876543210',
            'first_name' => 'Sparsh',
            'last_name' => 'Thakur',
        ])->assertRedirect();

        $contact = Contact::query()->where('phone', '+919876543210')->first();
        $this->assertNotNull($contact);
        $this->assertSame('Sparsh Thakur', $contact->name);
    }

    public function test_form_stats_are_tracked_on_cached_columns(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $form = SignupForm::factory()->active()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'template_id' => null,
            'submission_count' => 0,
            'sent_count' => 0,
        ]);

        for ($i = 0; $i < 3; $i++) {
            $this->post(route('public.form.submit', $this->publicFormParams($form->slug)), [
                'phone' => '91987654321'.$i,
            ]);
        }

        $form->refresh();
        $this->assertSame(3, $form->submission_count);
    }

    public function test_public_form_url_includes_tenant_id(): void
    {
        $form = SignupForm::factory()->active()->create([
            'whatsapp_line_id' => $this->testLine->id,
        ]);

        $this->assertStringContainsString('/form/'.$this->testTenant->id.'/', $form->publicUrl());
    }

    public function test_public_form_renders_legacy_pascal_case_fields(): void
    {
        $form = SignupForm::factory()->active()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'fields' => [
                [
                    'id' => 'phone_number',
                    'type' => 'Input',
                    'label' => 'Whatsapp Number',
                    'name' => 'phone_number',
                    'required' => true,
                ],
                [
                    'id' => 'first_name',
                    'type' => 'Input',
                    'label' => 'First Name',
                    'name' => 'first_name',
                    'required' => true,
                ],
                [
                    'id' => 'header-1',
                    'type' => 'Header',
                    'label' => 'Legacy Header Title',
                ],
                [
                    'id' => 'city',
                    'type' => 'Select',
                    'label' => 'City',
                    'options' => ['Delhi', 'Mumbai'],
                ],
            ],
        ]);

        $this->get(route('public.form.show', $this->publicFormParams($form->slug)))
            ->assertOk()
            ->assertSee('Legacy Header Title')
            ->assertSee('Whatsapp Number')
            ->assertSee('First Name')
            ->assertSee('City')
            ->assertSee('name="phone"', false)
            ->assertSee('name="first_name"', false)
            ->assertSee('Delhi')
            ->assertSee('Mumbai');
    }
}
