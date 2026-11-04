<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domains\Admin\Services\RegistrationCertificateService;
use App\Models\Admin;
use App\Models\CustomerReadinessSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Mockery;
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

        $this->fakeHttp([
            'https://ready-biz.test/*' => Http::response('ok', 200),
            'https://www.facebook.com/*' => Http::response('<html>ok</html>', 200),
            '*' => Http::response('ok', 200),
        ]);

        $this->mockValidCertificateExtract();
    }

    /**
     * Replace Http stubs entirely (Laravel merges consecutive Http::fake calls).
     *
     * @param  array<string, mixed>  $fakes
     */
    private function fakeHttp(array $fakes): void
    {
        $factory = new HttpFactory;
        $this->app->instance(HttpFactory::class, $factory);
        Http::swap($factory);
        Http::fake($fakes);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function mockValidCertificateExtract(array $overrides = []): void
    {
        $result = array_merge([
            'valid' => true,
            'message' => 'Certificate verified. GST / MSME details extracted.',
            'business_name' => 'Sharma Traders',
            'registration_number' => '29ABCDE1234F1Z5',
            'doc_type' => 'GST certificate',
            'document_type' => 'GST',
        ], $overrides);

        $mock = Mockery::mock(RegistrationCertificateService::class);
        $mock->shouldReceive('extractFromUpload')->andReturn($result);
        $this->app->instance(RegistrationCertificateService::class, $mock);
    }

    private function certificatePdf(): UploadedFile
    {
        return UploadedFile::fake()->create('gst-certificate.pdf', 120, 'application/pdf');
    }

    /**
     * @return array<string, mixed>
     */
    private function eligiblePayload(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Riya Sharma',
            'customer_email' => 'riya@personal.test',
            'personal_number' => '+919876543210',
            'business_name' => 'Will Be Overwritten',
            'business_email' => 'ops@sharma-biz.test',
            'site' => 'https://ready-biz.test',
            'docType' => 'GST certificate',
            'registration_number' => '29ABCDE1234F1Z5',
            'facebook_page_url' => 'https://www.facebook.com/SharmaTraders',
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
            'regdoc_file' => $this->certificatePdf(),
        ], $overrides);
    }

    public function test_eligible_submission_persists_and_redirects_to_result(): void
    {
        $response = $this->post(route('customer.readiness.store'), $this->eligiblePayload());

        $response->assertRedirect(route('customer.readiness.result'));
        $response->assertSessionHas('readiness_result.eligible', true);

        $submission = CustomerReadinessSubmission::query()->firstOrFail();
        $this->assertSame('eligible', $submission->status);
        $this->assertSame('Sharma Traders', $submission->business_name);
        $this->assertSame('ops@sharma-biz.test', $submission->business_email);
        $this->assertTrue((bool) data_get($submission->meta, 'eligible'));
        $this->assertSame('1234567890', data_get($submission->data, 'Business Portfolio ID'));
        $this->assertSame('29ABCDE1234F1Z5', data_get($submission->data, 'GST / MSME Number'));
        $this->assertSame('https://www.facebook.com/SharmaTraders', data_get($submission->data, 'Facebook Page URL'));
        $this->assertSame('+919876543210', data_get($submission->data, 'Personal Number'));

        $this->get(route('customer.readiness.result'))
            ->assertOk()
            ->assertSee('ready for onboarding', false);
    }

    public function test_not_eligible_when_voip_or_missing_meta_admin(): void
    {
        $this->post(route('customer.readiness.store'), $this->eligiblePayload([
            'mbm_admin' => 'employee',
            'num_type' => 'virtual',
            'fb_page_created' => 'no',
            'fb_page_name' => null,
        ]))->assertRedirect(route('customer.readiness.result'))
            ->assertSessionHas('readiness_result.eligible', false);

        $submission = CustomerReadinessSubmission::query()->firstOrFail();
        $this->assertSame('not_eligible', $submission->status);
        $this->assertFalse((bool) data_get($submission->meta, 'eligible'));
        $this->assertNotEmpty(data_get($submission->meta, 'reasons'));
    }

    public function test_rejects_free_business_email_domains(): void
    {
        $this->post(route('customer.readiness.store'), $this->eligiblePayload([
            'business_email' => 'ops@gmail.com',
        ]))->assertSessionHasErrors('business_email');
    }

    public function test_rejects_when_certificate_missing(): void
    {
        $payload = $this->eligiblePayload();
        unset($payload['regdoc_file']);

        $response = $this->from(route('customer.readiness'))
            ->post(route('customer.readiness.store'), $payload);

        $response->assertRedirect(route('customer.readiness'));
        $response->assertSessionHasErrors('regdoc_file');
    }

    public function test_rejects_when_certificate_extract_fails(): void
    {
        $this->mockValidCertificateExtract([
            'valid' => false,
            'message' => 'This does not look like a GST or Udyam MSME certificate.',
            'business_name' => null,
            'registration_number' => null,
            'doc_type' => null,
            'document_type' => null,
        ]);

        $response = $this->from(route('customer.readiness'))
            ->post(route('customer.readiness.store'), $this->eligiblePayload());

        $response->assertRedirect(route('customer.readiness'));
        $response->assertSessionHasErrors(['regdoc_file', 'registration_number', 'business_name']);
    }

    public function test_rejects_unverifiable_facebook_page_url(): void
    {
        $this->fakeHttp([
            'https://ready-biz.test/*' => Http::response('ok', 200),
            'https://www.facebook.com/*' => Http::response("This content isn't available", 404),
            '*' => Http::response('ok', 200),
        ]);
        $this->mockValidCertificateExtract();

        $response = $this->from(route('customer.readiness'))
            ->post(route('customer.readiness.store'), $this->eligiblePayload());

        $response->assertRedirect(route('customer.readiness'));
        $response->assertSessionHasErrors('facebook_page_url');
    }

    public function test_extract_registration_endpoint(): void
    {
        $this->post(route('customer.readiness.extract.registration'), [
            'regdoc_file' => $this->certificatePdf(),
        ])->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('business_name', 'Sharma Traders')
            ->assertJsonPath('registration_number', '29ABCDE1234F1Z5');
    }

    public function test_validate_business_email_endpoint(): void
    {
        $this->postJson(route('customer.readiness.validate.business-email'), [
            'business_email' => 'hello@gmail.com',
        ])->assertStatus(422)
            ->assertJson(['valid' => false]);

        $this->postJson(route('customer.readiness.validate.business-email'), [
            'business_email' => 'hello@acme-corp.test',
        ])->assertOk()
            ->assertJson(['valid' => true]);
    }

    public function test_validate_facebook_page_endpoint(): void
    {
        $this->postJson(route('customer.readiness.validate.facebook-page'), [
            'page_name' => 'https://www.facebook.com/AcmeCorp',
        ])->assertOk()
            ->assertJsonPath('valid', true);
    }

    public function test_json_api_store_returns_eligibility(): void
    {
        $this->post(route('api.customer-readiness.store'), $this->eligiblePayload())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('eligible', true);

        $this->assertSame(1, CustomerReadinessSubmission::query()->count());
    }

    public function test_admin_can_view_eligibility_meta(): void
    {
        $this->post(route('customer.readiness.store'), $this->eligiblePayload());
        $submission = CustomerReadinessSubmission::query()->firstOrFail();

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.submissions.readiness.show', $submission))
            ->assertOk()
            ->assertSee('Eligible')
            ->assertSee('Yes')
            ->assertSee('ops@sharma-biz.test');
    }
}
