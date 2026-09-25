<?php

namespace Tests\Unit\Templates;

use App\Domains\Templates\Support\TemplateNameValidator;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TemplateNameValidatorTest extends TestCase
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

    public function test_it_detects_duplicate_names_on_active_templates(): void
    {
        Template::factory()->draft()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'name' => 'welcome_offer',
            'code' => 'welcome_offer',
        ]);

        $this->assertTrue(
            TemplateNameValidator::nameExistsForLine('welcome_offer', $this->testLine->id),
        );
    }

    public function test_it_ignores_soft_deleted_never_submitted_drafts(): void
    {
        $deleted = Template::factory()->draft()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'name' => 'welcome_offer',
            'code' => 'welcome_offer',
            'payload' => array_merge(Template::defaultPayload(), [
                'meta' => [
                    'name' => 'welcome_offer',
                    'category' => 'MARKETING',
                    'language' => 'en_GB',
                    'template_type' => 'regular',
                    'setup_completed' => true,
                ],
            ]),
        ]);

        $deleted->delete();
        $deleted->refresh();

        $this->assertNull($deleted->code);
        $this->assertSame('welcome_offer', data_get($deleted->payload, 'meta.archived_code'));

        $this->assertFalse(
            TemplateNameValidator::nameExistsForLine('welcome_offer', $this->testLine->id),
        );
    }

    public function test_it_blocks_names_soft_deleted_with_provider_code_within_cooldown(): void
    {
        $deleted = Template::factory()->draft()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'name' => 'promo_blast',
            'code' => '1263007766488379392',
            'synced_at' => now()->subDay(),
            'payload' => array_merge(Template::defaultPayload(), [
                'meta' => [
                    'name' => 'promo_blast',
                    'category' => 'MARKETING',
                    'language' => 'en_GB',
                    'template_type' => 'regular',
                    'setup_completed' => true,
                ],
            ]),
        ]);

        $deleted->delete();
        $deleted->refresh();

        $this->assertTrue(
            TemplateNameValidator::nameBlockedByMetaCooldown('promo_blast', $this->testLine->id),
        );
        $this->assertTrue(
            TemplateNameValidator::nameExistsForLine('promo_blast', $this->testLine->id),
        );
    }
}
