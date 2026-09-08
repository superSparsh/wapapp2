<?php

namespace Tests\Feature\HelpCenter;

use App\Domains\HelpCenter\Support\HelpCenterCache;
use App\Models\Faq;
use App\Models\TeamMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class FaqTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        HelpCenterCache::flush();
        $this->setUpTenant();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_owner_can_view_active_faqs(): void
    {
        Faq::factory()->create([
            'heading' => 'Messaging Limits',
            'slug' => 'messaging-limits',
            'description' => '<p>Daily limits depend on quality rating.</p>',
        ]);

        Faq::factory()->inactive()->create([
            'heading' => 'Hidden FAQ',
            'slug' => 'hidden-faq',
        ]);

        $this->actingAsTenantUser()
            ->get(route('faqs.index'))
            ->assertOk()
            ->assertSee('Messaging Limits')
            ->assertDontSee('Hidden FAQ');
    }

    public function test_owner_can_open_faq_by_slug(): void
    {
        Faq::factory()->create([
            'heading' => 'Template Categorization',
            'slug' => 'template-categorization',
            'description' => '<p>Marketing templates are flexible.</p>',
        ]);

        $this->actingAsTenantUser()
            ->get(route('faqs.show', 'template-categorization'))
            ->assertOk()
            ->assertSee('Template Categorization')
            ->assertSee('Marketing templates are flexible.');
    }

    public function test_owner_can_search_faqs(): void
    {
        Faq::factory()->create([
            'heading' => 'Messaging Quality',
            'slug' => 'messaging-quality',
        ]);

        Faq::factory()->create([
            'heading' => 'Wallet Billing',
            'slug' => 'wallet-billing',
        ]);

        $this->actingAsTenantUser()
            ->get(route('faqs.index', ['q' => 'wallet']))
            ->assertOk()
            ->assertSee('Wallet Billing')
            ->assertDontSee('Messaging Quality');
    }

    public function test_team_member_cannot_access_faqs(): void
    {
        $member = TeamMember::factory()->create([
            'parent_user_id' => $this->testUser->id,
        ]);

        $this->actingAsTeamMember($member)
            ->get(route('faqs.index'))
            ->assertForbidden();
    }
}
