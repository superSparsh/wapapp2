<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Models\WhatsappLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class AddChatappPhoneNumberTest extends TestCase
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

    public function test_add_number_form_requires_connected_default_line(): void
    {
        $this->actingAsTenantUser()
            ->get(route('profile.phone-lines.add'))
            ->assertRedirect(route('profile.phone-lines.index'));
    }

    public function test_store_creates_whatsapp_line_when_cams_succeeds(): void
    {
        config([
            'whatsapp.alibaba.access_key_id' => 'test-key',
            'whatsapp.alibaba.access_key_secret' => 'test-secret',
            'whatsapp.alibaba.endpoint' => 'cams.test.local',
        ]);

        Http::fake([
            'https://cams.test.local/*' => Http::response([
                'Code' => 'OK',
                'Message' => 'success',
                'RequestId' => 'req-1',
            ], 200),
        ]);

        $this->testLine->update([
            'waba_id' => 'waba-1',
            'alibaba_cust_space_id' => 'space-1',
        ]);

        $response = $this->actingAsTenantUser()
            ->from(route('profile.phone-lines.add'))
            ->post(route('profile.phone-lines.add.store'), [
                'country_code' => '91',
                'phone_number' => '9876543210',
                'verified_name' => 'Sales Line',
            ]);

        $response->assertRedirect(route('profile.phone-lines.index'));

        $this->assertDatabaseHas('whatsapp_lines', [
            'phone' => '919876543210',
            'display_name' => 'Sales Line',
            'alibaba_cust_space_id' => 'space-1',
            'waba_id' => 'waba-1',
        ]);
    }

    public function test_store_fails_loudly_when_cams_rejects(): void
    {
        config([
            'whatsapp.alibaba.access_key_id' => 'test-key',
            'whatsapp.alibaba.access_key_secret' => 'test-secret',
            'whatsapp.alibaba.endpoint' => 'cams.test.local',
        ]);

        Http::fake([
            'https://cams.test.local/*' => Http::response([
                'Code' => 'InvalidParameter',
                'Message' => 'Phone already registered',
            ], 200),
        ]);

        $this->testLine->update([
            'waba_id' => 'waba-1',
            'alibaba_cust_space_id' => 'space-1',
        ]);

        $this->actingAsTenantUser()
            ->from(route('profile.phone-lines.add'))
            ->post(route('profile.phone-lines.add.store'), [
                'country_code' => '91',
                'phone_number' => '9876543210',
                'verified_name' => 'Sales Line',
            ])
            ->assertRedirect(route('profile.phone-lines.add'))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('whatsapp_lines', [
            'phone' => '919876543210',
        ]);
    }
}
