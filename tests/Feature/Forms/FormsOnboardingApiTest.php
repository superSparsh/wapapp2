<?php

declare(strict_types=1);

namespace Tests\Feature\Forms;

use App\Enums\BillingCycle;
use App\Enums\TenantUserAccountType;
use App\Models\CustomerOnboardingSubmission;
use App\Models\CustomerReadinessSubmission;
use App\Models\Plan;
use App\Models\TenantUserAccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FormsOnboardingApiTest extends TestCase
{
    use RefreshDatabase;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Mail::fake();

        $this->plan = Plan::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Ginger Basic',
            'slug' => 'ginger-basic',
            'description' => 'Forms onboarding test plan',
            'price' => 0,
            'starting_wallet_balance' => 0,
            'currency' => 'INR',
            'billing_cycle' => BillingCycle::Yearly,
            'validity_days' => 365,
            'messages_limit' => 10000,
            'contacts_limit' => 5000,
            'team_members_limit' => 5,
            'whatsapp_lines_limit' => 2,
            'sort_order' => 1,
            'is_active' => true,
            'features' => [],
        ]);
    }

    public function test_forms_onboarding_creates_tenant_and_returns_credentials(): void
    {
        $pdf = UploadedFile::fake()->create('gst.pdf', 120, 'application/pdf');

        $response = $this->post(route('api.forms.onboarding'), [
            'customer_name' => 'Riya Sharma',
            'customer_email' => 'riya@sharma-biz.test',
            'business_name' => 'Sharma Traders',
            'business_email' => 'ops@sharma-biz.test',
            'docType' => 'GST certificate',
            'regdoc_file' => $pdf,
            'number' => '+919876543210',
            'whatsapp_display_name' => 'Sharma Traders',
            'first_name' => 'Riya',
            'last_name' => 'Sharma',
            'pan' => 'ABCDE1234F',
            'gstin' => '29ABCDE1234F1Z5',
            'addr1' => '12 MG Road',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pin' => '560001',
            'tittu_plan_type' => 'Ginger Basic',
            'meta_bm_id' => '123456789012345',
            'primary_use_cases' => 'Marketing',
            'site' => 'https://sharma-biz.test',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.login_email', 'ops@sharma-biz.test')
            ->assertJsonPath('data.is_new_user', true);

        $data = $response->json('data');
        $this->assertNotEmpty($data['temp_password'] ?? null);
        $this->assertNotEmpty($data['user_id'] ?? null);
        $this->assertNotEmpty($data['customer_id'] ?? null);
        $this->assertNotEmpty($data['submission_id'] ?? null);

        $this->assertDatabaseHas('tenant_user_access', [
            'email' => 'ops@sharma-biz.test',
            'account_type' => TenantUserAccountType::Owner->value,
            'is_active' => true,
        ], config('tenancy.database.central_connection'));

        $this->assertSame(1, CustomerReadinessSubmission::query()->count());
        $this->assertSame(1, CustomerOnboardingSubmission::query()->where('status', 'completed')->count());

        $access = TenantUserAccess::findActiveByEmail('ops@sharma-biz.test');
        $this->assertNotNull($access);
        tenancy()->initialize($access->tenant_id);
        $user = User::query()->where('email', 'ops@sharma-biz.test')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check($data['temp_password'], $user->password));
        tenancy()->end();
    }

    public function test_forms_onboarding_reuses_existing_email_and_resets_password(): void
    {
        $first = $this->post(route('api.forms.onboarding'), $this->payload());
        $first->assertOk();
        $firstPassword = (string) $first->json('data.temp_password');
        $tenantId = (string) $first->json('data.customer_id');

        $second = $this->post(route('api.forms.onboarding'), $this->payload([
            'regdoc_file' => UploadedFile::fake()->create('gst2.pdf', 80, 'application/pdf'),
        ]));

        $second->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_new_user', false)
            ->assertJsonPath('data.customer_id', $tenantId);

        $secondPassword = (string) $second->json('data.temp_password');
        $this->assertNotSame($firstPassword, $secondPassword);

        tenancy()->initialize($tenantId);
        $user = User::query()->where('email', 'ops@sharma-biz.test')->firstOrFail();
        $this->assertTrue(Hash::check($secondPassword, $user->password));
        tenancy()->end();
    }

    public function test_forms_onboarding_requires_regdoc_and_pan(): void
    {
        $this->post(route('api.forms.onboarding'), [
            'customer_name' => 'Riya Sharma',
            'customer_email' => 'riya@sharma-biz.test',
            'business_name' => 'Sharma Traders',
            'business_email' => 'ops@sharma-biz.test',
            'number' => '+919876543210',
            'whatsapp_display_name' => 'Sharma Traders',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Riya Sharma',
            'customer_email' => 'riya@sharma-biz.test',
            'business_name' => 'Sharma Traders',
            'business_email' => 'ops@sharma-biz.test',
            'docType' => 'GST certificate',
            'regdoc_file' => UploadedFile::fake()->create('gst.pdf', 120, 'application/pdf'),
            'number' => '+919876543210',
            'whatsapp_display_name' => 'Sharma Traders',
            'first_name' => 'Riya',
            'last_name' => 'Sharma',
            'pan' => 'ABCDE1234F',
            'addr1' => '12 MG Road',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pin' => '560001',
            'tittu_plan_type' => 'ginger_basic',
            'meta_bm_id' => '123456789012345',
        ], $overrides);
    }
}
