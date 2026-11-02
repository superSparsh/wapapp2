<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\BusinessCustomTermStatus;
use App\Models\Admin;
use App\Models\BusinessCustomTerm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class DpdpReadinessTest extends TestCase
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

    public function test_admin_can_view_platform_dpdp_readiness(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Legal Admin',
            'email' => 'legal-admin@wapapp.test',
            'password' => 'password',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.dpdp-readiness'))
            ->assertOk()
            ->assertSee('DPDP / Legal readiness')
            ->assertSee(config('legal.grievance.email'))
            ->assertSee('Privacy / Terms / Breach playbook pages')
            ->assertSee('Consent event ledger')
            ->assertSee('Rights + grievance request workflow')
            ->assertSee('OFF (draft)');
    }

    public function test_customer_show_includes_dpdp_checklist_and_custom_terms_pdf(): void
    {
        BusinessCustomTerm::query()->create([
            'tenant_id' => (string) tenant('id'),
            'title' => 'Shop Terms',
            'body' => 'Customers agree to receive updates.',
            'status' => BusinessCustomTermStatus::PendingReview,
            'version' => 1,
            'submitted_at' => now(),
        ]);

        $admin = Admin::query()->create([
            'name' => 'Review Admin',
            'email' => 'review-dpdp@wapapp.test',
            'password' => 'password',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.show', tenant()))
            ->assertOk()
            ->assertSee('DPDP / legal for this customer')
            ->assertSee('Custom customer terms');

        $pdf = $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.custom-terms.pdf', tenant()));

        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $pdf->streamedContent());
    }
}
