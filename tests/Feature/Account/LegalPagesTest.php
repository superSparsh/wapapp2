<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class LegalPagesTest extends TestCase
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

    public function test_guest_is_redirected_from_privacy_and_terms(): void
    {
        $this->get(route('profile.privacy-policy'))->assertRedirect();
        $this->get(route('profile.terms'))->assertRedirect();
    }

    public function test_authenticated_user_can_view_privacy_policy(): void
    {
        $this->actingAsTenantUser()
            ->get(route('profile.privacy-policy'))
            ->assertOk()
            ->assertSee('Privacy Policy')
            ->assertSee('Data Fiduciary', false)
            ->assertSee('Digital Personal Data Protection Act', false)
            ->assertSee('Singapore', false)
            ->assertSee('Alibaba Cloud', false)
            ->assertSee('Internal review draft', false)
            ->assertSee(config('legal.grievance.email'));
    }

    public function test_authenticated_user_can_view_terms(): void
    {
        $this->actingAsTenantUser()
            ->get(route('profile.terms'))
            ->assertOk()
            ->assertSee('Terms of Service')
            ->assertSee('Data Processor', false)
            ->assertSee('Singapore', false)
            ->assertSee('WhatsApp Business', false)
            ->assertSee('Internal review draft', false);
    }
}
