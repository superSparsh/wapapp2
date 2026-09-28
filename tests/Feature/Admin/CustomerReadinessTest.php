<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\CustomerReadinessSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CustomerReadinessTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::query()->create([
            'name' => 'Readiness Admin',
            'email' => 'admin-readiness@wapapp.test',
            'password' => 'password',
            'is_active' => true,
        ]);

        Http::fake([
            'https://ready-biz.test/*' => Http::response('ok', 200),
            'https://www.facebook.com/*' => Http::response('<html>ok</html>', 200),
            '*' => Http::response('ok', 200),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function eligiblePayload(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Riya Sharma',
            'customer_email' => 'riya@personal.test',
            'business_name' => 'Sharma Traders',
            'business_email' => 'ops@sharma-biz.test',
            'site' => 'https://ready-biz.test',
            'docType' => 'GST certificate',
            'mbm_admin' => 'admin',
            'mbm_mfa' => 'all_admins',
            'fb_personal' => 'available',
            'fb_page_created' => 'yes',
            'fb_page_name' => 'https://business.facebook.com/latest/settings/business_info?business_id=1234567890',
            'num_plan' => 'new_unused',
            'num_otp' => 'can_receive',
            'num_type' => 'physical',
            'number' => '+919876543210',
            'whatsapp_display_name' => 'Sharma Traders',
        ], $overrides);
    }

    public function test_eligible_submission_persists_and_redirects_to_result(): void
    {
        $response = $this->post(route('customer-readiness.store'), $this->eligiblePayload());

        $response->assertRedirect(route('customer-readiness.result'));
        $response->assertSessionHas('readiness_result.eligible', true);

        $submission = CustomerReadinessSubmission::query()->firstOrFail();
        $this->assertSame('eligible', $submission->status);
        $this->assertSame('Sharma Traders', $submission->business_name);
        $this->assertSame('ops@sharma-biz.test', $submission->business_email);
        $this->assertTrue((bool) data_get($submission->meta, 'eligible'));
        $this->assertSame('1234567890', data_get($submission->data, 'Business Portfolio ID'));

        $this->get(route('customer-readiness.result'))
            ->assertOk()
            ->assertSee('ready for onboarding', false);
    }

    public function test_not_eligible_when_voip_or_missing_meta_admin(): void
    {
        $this->post(route('customer-readiness.store'), $this->eligiblePayload([
            'mbm_admin' => 'employee',
            'num_type' => 'virtual',
            'fb_page_created' => 'no',
            'fb_page_name' => null,
        ]))->assertRedirect(route('customer-readiness.result'))
            ->assertSessionHas('readiness_result.eligible', false);

        $submission = CustomerReadinessSubmission::query()->firstOrFail();
        $this->assertSame('not_eligible', $submission->status);
        $this->assertFalse((bool) data_get($submission->meta, 'eligible'));
        $this->assertNotEmpty(data_get($submission->meta, 'reasons'));
    }

    public function test_rejects_free_business_email_domains(): void
    {
        $this->post(route('customer-readiness.store'), $this->eligiblePayload([
            'business_email' => 'ops@gmail.com',
        ]))->assertSessionHasErrors('business_email');
    }

    public function test_validate_business_email_endpoint(): void
    {
        $this->postJson(route('customer-readiness.validate.business-email'), [
            'business_email' => 'hello@gmail.com',
        ])->assertStatus(422)
            ->assertJson(['valid' => false]);

        $this->postJson(route('customer-readiness.validate.business-email'), [
            'business_email' => 'hello@acme-corp.test',
        ])->assertOk()
            ->assertJson(['valid' => true]);
    }

    public function test_validate_facebook_page_endpoint(): void
    {
        $this->postJson(route('customer-readiness.validate.facebook-page'), [
            'page_name' => 'Acme Corp',
        ])->assertOk()
            ->assertJsonPath('valid', true);
    }

    public function test_json_api_store_returns_eligibility(): void
    {
        $this->postJson(route('api.customer-readiness.store'), $this->eligiblePayload())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('eligible', true);

        $this->assertSame(1, CustomerReadinessSubmission::query()->count());
    }

    public function test_admin_can_view_eligibility_meta(): void
    {
        $this->post(route('customer-readiness.store'), $this->eligiblePayload());
        $submission = CustomerReadinessSubmission::query()->firstOrFail();

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.submissions.readiness.show', $submission))
            ->assertOk()
            ->assertSee('Eligible')
            ->assertSee('Yes')
            ->assertSee('ops@sharma-biz.test');
    }
}
