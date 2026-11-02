<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Enums\DataPrincipalRequestStatus;
use App\Enums\DataPrincipalRequestType;
use App\Models\Admin;
use App\Models\DataPrincipalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class PrivacyRightsTest extends TestCase
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

    public function test_authenticated_user_can_view_and_submit_rights_request(): void
    {
        $this->actingAsTenantUser();

        $this->get(route('profile.privacy-rights'))
            ->assertOk()
            ->assertSee('Privacy rights')
            ->assertSee('Personal data collection notice', false);

        $this->post(route('profile.privacy-rights.store'), [
            'request_type' => DataPrincipalRequestType::Access->value,
            'description' => 'Please share what personal data you hold about my account.',
            'requester_phone' => '9999999999',
        ])->assertRedirect(route('profile.privacy-rights'));

        $stored = DataPrincipalRequest::query()
            ->where('requester_email', strtolower((string) $this->testUser->email))
            ->first();

        $this->assertNotNull($stored);
        $this->assertSame(DataPrincipalRequestType::Access, $stored->request_type);
        $this->assertSame(DataPrincipalRequestStatus::Open, $stored->status);
    }

    public function test_admin_can_update_rights_request_status(): void
    {
        $request = DataPrincipalRequest::query()->create([
            'tenant_id' => (string) tenant('id'),
            'requester_name' => 'Test User',
            'requester_email' => 'rights@wapapp.test',
            'request_type' => DataPrincipalRequestType::Grievance,
            'description' => 'Need help with a privacy concern about my account data.',
            'status' => DataPrincipalRequestStatus::Open,
            'due_at' => now()->addDays(7),
        ]);

        $admin = Admin::query()->create([
            'name' => 'Rights Admin',
            'email' => 'rights-admin@wapapp.test',
            'password' => 'password',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.rights-requests.index'))
            ->assertOk()
            ->assertSee('rights@wapapp.test');

        $this->actingAs($admin, 'admin')
            ->put(route('admin.rights-requests.update', $request), [
                'status' => DataPrincipalRequestStatus::Resolved->value,
                'admin_note' => 'Responded by email.',
            ])
            ->assertRedirect(route('admin.rights-requests.index'));

        $request->refresh();
        $this->assertSame(DataPrincipalRequestStatus::Resolved, $request->status);
        $this->assertNotNull($request->resolved_at);
        $this->assertSame('Responded by email.', $request->admin_note);
    }
}
