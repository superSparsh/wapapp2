<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Enums\BusinessCustomTermStatus;
use App\Enums\TenantStatus;
use App\Models\Admin;
use App\Models\BusinessCustomTerm;
use App\Models\CustomerOnboardingSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class BusinessTermsTest extends TestCase
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

    public function test_business_can_view_and_submit_custom_terms(): void
    {
        $this->actingAsTenantUser()
            ->get(route('profile.business-terms'))
            ->assertOk()
            ->assertSee('Business Terms')
            ->assertSee('Signed onboarding agreement')
            ->assertSee(config('legal.meta_policies.business_terms.label'));

        $this->actingAsTenantUser()
            ->put(route('profile.business-terms.update'), [
                'title' => 'Our Customer Terms',
                'body' => 'You agree to receive WhatsApp updates from us.',
                'submit_for_review' => '1',
            ])
            ->assertRedirect(route('profile.business-terms'));

        $term = BusinessCustomTerm::query()->where('tenant_id', tenant('id'))->first();
        $this->assertNotNull($term);
        $this->assertSame(BusinessCustomTermStatus::PendingReview, $term->status);
        $this->assertSame('Our Customer Terms', $term->title);
    }

    public function test_admin_can_remove_custom_terms_and_suspend(): void
    {
        Mail::fake();

        $term = BusinessCustomTerm::query()->create([
            'tenant_id' => (string) tenant('id'),
            'title' => 'Bad Terms',
            'body' => 'We sell your data.',
            'status' => BusinessCustomTermStatus::PendingReview,
            'version' => 1,
            'submitted_at' => now(),
        ]);

        $admin = Admin::query()->create([
            'name' => 'Review Admin',
            'email' => 'review-admin@wapapp.test',
            'password' => 'password',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.custom-terms.remove', tenant()), [
                'admin_note' => 'Prohibited claims about selling data.',
                'suspend' => '1',
            ])
            ->assertRedirect();

        $term->refresh();
        $this->assertSame(BusinessCustomTermStatus::Removed, $term->status);
        $this->assertSame(TenantStatus::Suspended, tenant()->fresh()->status);
    }

    public function test_signed_pdf_downloads_when_on_file(): void
    {
        $dir = storage_path('app/public/forms/signatures');
        File::ensureDirectoryExists($dir);
        $path = $dir.'/test_signed_agreement.pdf';
        File::put($path, "%PDF-1.4\n% test");

        CustomerOnboardingSubmission::query()->create([
            'company_name' => 'Test Co',
            'email' => (string) tenant('email'),
            'status' => 'completed',
            'attachment_paths' => [$path],
            'zoho_response' => [
                'data' => ['customer_id' => (string) tenant('id')],
            ],
        ]);

        $this->actingAsTenantUser()
            ->get(route('profile.business-terms.signed-pdf'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        File::delete($path);
    }

    public function test_signup_steps_link_meta_policy_documents(): void
    {
        $this->get(route('signup.step-3'))
            ->assertOk()
            ->assertSee(config('legal.meta_policies.business_terms.pdf_url'), false);

        $this->get(route('signup.step-4'))
            ->assertOk()
            ->assertSee(config('legal.meta_policies.commerce_policy.pdf_url'), false);
    }
}
