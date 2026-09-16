<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Domains\Templates\Console\Commands\DeleteSoftDeletedTemplates;
use App\Domains\Templates\Console\Commands\SubmitPendingTemplates;
use App\Domains\Templates\Enums\TemplateStatus;
use App\Domains\Templates\Jobs\DeleteTemplateJob;
use App\Domains\Templates\Jobs\SubmitTemplateJob;
use App\Domains\Templates\Services\TemplateBuilderService;
use App\Models\Campaign;
use App\Models\Template;
use App\Models\Variable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TemplateOperativeParityTest extends TestCase
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

    public function test_body_variable_sync_preserves_button_pivots_and_auto_creates(): void
    {
        $payment = Variable::factory()->create([
            'name' => 'payment_link',
            'whatsapp_line_id' => $this->testLine->id,
        ]);

        $template = Template::factory()->draft()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'team_member_id' => null,
        ]);

        $template->variables()->attach($payment->id, [
            'placement' => 'buttons',
            'position' => 0,
        ]);

        app(TemplateBuilderService::class)->saveStep($template, 'body', [
            'text' => 'Hello $(customer_name), pay via $(payment_link)',
            'samples' => [],
        ]);

        $template->refresh()->load('variables');

        $this->assertTrue(
            $template->variables->contains(fn (Variable $v) => $v->name === 'payment_link'
                && ($v->pivot->placement ?? null) === 'buttons')
        );
        $this->assertTrue(
            $template->variables->contains(fn (Variable $v) => $v->name === 'customer_name'
                && ($v->pivot->placement ?? null) === 'body')
        );
        $this->assertDatabaseHas('variables', [
            'name' => 'customer_name',
            'whatsapp_line_id' => $this->testLine->id,
        ]);
    }

    public function test_duplicate_creates_draft_without_whatsapp_code(): void
    {
        $template = Template::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'name' => 'promo_offer',
            'code' => 'WA_CODE_123',
            'status' => TemplateStatus::Approved,
            'body_preview' => 'Hi $(name)',
        ]);

        $this->actingAsTenantUser()
            ->post(route('templates.duplicate', $template))
            ->assertRedirect();

        $copy = Template::query()->where('name', 'like', 'promo_offer_copy%')->first();
        $this->assertNotNull($copy);
        $this->assertSame(TemplateStatus::Draft, $copy->status);
        $this->assertNull($copy->code);
        $this->assertNotSame($template->uuid, $copy->uuid);
    }

    public function test_delete_blocked_when_template_used_in_campaign(): void
    {
        $template = Template::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
        ]);
        Campaign::factory()->create([
            'template_id' => $template->id,
            'whatsapp_line_id' => $this->testLine->id,
        ]);

        $this->actingAsTenantUser()
            ->from(route('templates.index'))
            ->delete(route('templates.destroy', $template))
            ->assertRedirect(route('templates.index'))
            ->assertSessionHasErrors('template');

        $this->assertDatabaseHas('templates', [
            'id' => $template->id,
            'deleted_at' => null,
        ]);
    }

    public function test_soft_delete_command_dispatches_job_using_archived_code(): void
    {
        Bus::fake();

        $template = Template::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'code' => 'META_ABC',
        ]);
        $template->delete();

        $this->assertNull($template->fresh()->code);
        $this->assertSame('META_ABC', $template->fresh()->whatsappCode());

        $this->artisan(DeleteSoftDeletedTemplates::class)
            ->assertSuccessful();

        Bus::assertDispatched(DeleteTemplateJob::class, fn (DeleteTemplateJob $job) => $job->templateId === $template->id);
    }

    public function test_submit_pending_skips_already_synced_templates(): void
    {
        Bus::fake();

        Template::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'status' => TemplateStatus::PendingReview,
            'code' => 'ALREADY_SENT',
            'synced_at' => now()->subMinutes(10),
        ]);

        $unsynced = Template::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'status' => TemplateStatus::PendingReview,
            'code' => 'local_name',
            'synced_at' => null,
        ]);

        $this->artisan(SubmitPendingTemplates::class)
            ->assertSuccessful();

        Bus::assertDispatched(SubmitTemplateJob::class, fn (SubmitTemplateJob $job) => $job->templateId === $unsynced->id);
        Bus::assertDispatchedTimes(SubmitTemplateJob::class, 1);
    }
}
