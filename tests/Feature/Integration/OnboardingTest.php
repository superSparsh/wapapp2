<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Models\IsvTermsAcceptance;
use App\Models\WabaAccount;
use App\Models\WhatsappLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class OnboardingTest extends TestCase
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

    public function test_dashboard_redirects_to_onboarding_when_waba_not_bound(): void
    {
        $this->clearWhatsappConnection();

        $this->actingAsTenantUser()
            ->get(route('dashboard'))
            ->assertRedirect(route('onboarding.start'));
    }

    public function test_onboarding_start_redirects_to_business_step(): void
    {
        $this->clearWhatsappConnection();

        $this->actingAsTenantUser()
            ->get(route('onboarding.start'))
            ->assertRedirect(route('onboarding.business'));
    }

    public function test_business_step_renders_current_template(): void
    {
        $this->clearWhatsappConnection();

        $this->actingAsTenantUser()
            ->get(route('onboarding.business'))
            ->assertOk()
            ->assertSee('Business Details')
            ->assertSee('name="business_name"', false)
            ->assertSee('Logout');
    }

    public function test_isv_terms_not_found_when_empty(): void
    {
        $this->clearWhatsappConnection();

        $this->actingAsTenantUser()
            ->getJson(route('onboarding.isv-terms'))
            ->assertOk()
            ->assertJson([
                'status' => 'not_found',
                'data' => null,
            ]);
    }

    public function test_store_isv_terms_form_redirects_to_connect(): void
    {
        $this->clearWhatsappConnection();

        config([
            'whatsapp.alibaba.access_key_id' => 'test-key',
            'whatsapp.alibaba.access_key_secret' => 'test-secret',
            'whatsapp.alibaba.endpoint' => 'cams.test.local',
        ]);

        Http::fake([
            'https://cams.test.local/*' => Http::response([
                'Code' => 'OK',
                'AppId' => '324633502517598',
            ], 200),
        ]);

        $payload = [
            'business_name' => 'Acme Corp',
            'bm_id' => '1234567890',
            'website_email' => 'ops@acme.test',
            'use_case' => 'Order notifications',
            'business_address' => '1 Market Street',
        ];

        $this->actingAsTenantUser()
            ->post(route('onboarding.add-isv-terms'), $payload)
            ->assertRedirect(route('onboarding.connect'));

        $this->assertDatabaseHas('isv_terms_acceptances', [
            'business_name' => 'Acme Corp',
            'website_email' => 'ops@acme.test',
            'status' => 'Unverified',
        ]);

        $this->assertSame('324633502517598', session('onboarding.fb_app_id'));
    }

    public function test_connect_step_renders_after_business_details(): void
    {
        $this->clearWhatsappConnection();

        IsvTermsAcceptance::query()->create([
            'business_name' => 'Acme Corp',
            'bm_id' => '1234567890',
            'website_email' => 'ops@acme.test',
            'use_case' => 'Orders',
            'business_address' => '1 Market Street',
            'country_code' => 'IN',
            'status' => 'Unverified',
        ]);

        config([
            'whatsapp.alibaba.access_key_id' => 'test-key',
            'whatsapp.alibaba.access_key_secret' => 'test-secret',
            'whatsapp.alibaba.endpoint' => 'cams.test.local',
        ]);

        Http::fake([
            'https://cams.test.local/*' => Http::response([
                'Code' => 'OK',
                'AppId' => '324633502517598',
            ], 200),
        ]);

        $this->actingAsTenantUser()
            ->withSession(['onboarding.fb_app_id' => '324633502517598'])
            ->get(route('onboarding.connect'))
            ->assertOk()
            ->assertSee('Connect Your WhatsApp')
            ->assertSee('Connect WhatsApp Business API')
            ->assertSee('id="onboarding-connect-btn"', false);
    }

    public function test_embed_data_binds_waba_and_marks_registered(): void
    {
        $this->clearWhatsappConnection();

        IsvTermsAcceptance::query()->create([
            'business_name' => 'Acme Corp',
            'bm_id' => '1234567890',
            'website_email' => 'ops@acme.test',
            'use_case' => 'Orders',
            'business_address' => '1 Market Street',
            'country_code' => 'IN',
            'status' => 'Unverified',
        ]);

        config([
            'whatsapp.alibaba.access_key_id' => 'test-key',
            'whatsapp.alibaba.access_key_secret' => 'test-secret',
            'whatsapp.alibaba.endpoint' => 'cams.test.local',
        ]);

        Http::fake([
            'https://cams.test.local/*' => Http::sequence()
                ->push([
                    'Code' => 'OK',
                    'Data' => ['CustSpaceId' => 'SPACE-NEW'],
                ], 200)
                ->push([
                    'Code' => 'OK',
                    'Data' => ['CustSpaceId' => 'SPACE-NEW'],
                ], 200)
                ->push([
                    'Code' => 'OK',
                    'PhoneNumbers' => [[
                        'phoneNumber' => '919876543210',
                        'qualityRating' => 'GREEN',
                        'messagingLimitTier' => 'TIER_1K',
                        'verifiedName' => 'Acme Biz',
                        'status' => 'CONNECTED',
                    ]],
                ], 200)
                ->push(['Code' => 'OK'], 200)
                ->push([
                    'Code' => 'OK',
                    'Data' => [
                        'businessId' => 'BID1',
                        'businessName' => 'Acme Corp',
                        'verificationStatus' => 'verified',
                        'vertical' => 'OTHER',
                    ],
                ], 200),
        ]);

        $this->actingAsTenantUser()
            ->postJson(route('onboarding.embed-data'), [
                'waba_id' => 'WABA-999',
                'phone_number_id' => 'PN-1',
            ])
            ->assertOk()
            ->assertJson(['status' => '200', 'msg' => 'success'])
            ->assertJsonPath('redirect', route('onboarding.finish'));

        $this->assertDatabaseHas('waba_accounts', [
            'waba_id' => 'WABA-999',
            'alibaba_cust_space_id' => 'SPACE-NEW',
            'is_registered' => true,
        ]);

        $this->assertDatabaseHas('isv_terms_acceptances', [
            'website_email' => 'ops@acme.test',
            'status' => 'Verified',
        ]);

        $this->assertTrue(
            WhatsappLine::query()
                ->where('waba_id', 'WABA-999')
                ->where('alibaba_cust_space_id', 'SPACE-NEW')
                ->exists()
        );
    }

    public function test_finish_step_renders_when_complete(): void
    {
        $this->actingAsTenantUser()
            ->get(route('onboarding.finish'))
            ->assertOk()
            ->assertSee("You're all set!")
            ->assertSee('Go to Dashboard');
    }

    private function clearWhatsappConnection(): void
    {
        WhatsappLine::query()->update([
            'waba_id' => null,
            'alibaba_cust_space_id' => null,
        ]);
        WabaAccount::query()->delete();
    }
}
