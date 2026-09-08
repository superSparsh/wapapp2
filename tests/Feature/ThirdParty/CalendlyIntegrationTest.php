<?php

declare(strict_types=1);

namespace Tests\Feature\ThirdParty;

use App\Domains\ThirdParty\Enums\IntegrationStatus;
use App\Domains\ThirdParty\Jobs\SyncCalendlyEventsJob;
use App\Domains\ThirdParty\Models\CalendlyEvent;
use App\Domains\ThirdParty\Models\CalendlyIntegration;
use App\Domains\ThirdParty\Models\CalendlyMessageLog;
use App\Domains\ThirdParty\Models\CalendlyWebhookLog;
use App\Domains\ThirdParty\Services\CalendlyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CalendlyIntegrationTest extends TestCase
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

    // ─── Page accessibility ───────────────────────────────────────────────────

    public function test_calendly_index_requires_auth(): void
    {
        $this->get(route('integration.calendly'))->assertRedirect();
    }

    public function test_calendly_index_page_loads_with_tabs(): void
    {
        $this->actingAsTenantUser()
            ->get(route('integration.calendly'))
            ->assertOk()
            ->assertViewIs('integration.calendly')
            ->assertViewHas('activeTab', 'token');
    }

    public function test_active_tab_auto_detects_events_from_page_param(): void
    {
        $this->actingAsTenantUser()
            ->get(route('integration.calendly', ['page' => 2]))
            ->assertOk()
            ->assertViewHas('activeTab', 'events');
    }

    public function test_active_tab_auto_detects_logs_from_log_type_param(): void
    {
        $this->actingAsTenantUser()
            ->get(route('integration.calendly', ['log_type' => 'sent']))
            ->assertOk()
            ->assertViewHas('activeTab', 'logs');
    }

    // ─── Update / Token ───────────────────────────────────────────────────────

    public function test_update_rejects_missing_access_token(): void
    {
        $this->actingAsTenantUser()
            ->put(route('integration.calendly.update'), ['settings' => []])
            ->assertSessionHasErrors(['settings.access_token']);
    }

    public function test_update_rejects_short_token(): void
    {
        $this->actingAsTenantUser()
            ->put(route('integration.calendly.update'), ['settings' => ['access_token' => 'short']])
            ->assertSessionHasErrors(['settings.access_token']);
    }

    public function test_update_checks_for_duplicate_token(): void
    {
        $token = 'eyJhbGciOiJIUzI1NiJ9.validtoken123456789';

        // Existing integration with the same token under a different user
        CalendlyIntegration::query()->create([
            'user_id'  => 9999,
            'status'   => IntegrationStatus::Enabled,
            'settings' => ['access_token' => $token],
        ]);

        Http::fake([
            'https://api.calendly.com/users/me' => Http::response(['resource' => ['uri' => 'u', 'current_organization' => 'o', 'email' => 'a@b.com']], 200),
        ]);

        $this->actingAsTenantUser()
            ->put(route('integration.calendly.update'), ['settings' => ['access_token' => $token]])
            ->assertSessionHasErrors(['settings.access_token']);
    }

    // ─── Toggle ───────────────────────────────────────────────────────────────

    public function test_toggle_enables_integration(): void
    {
        $integration = CalendlyIntegration::query()->create([
            'user_id'  => $this->testUser->id,
            'status'   => IntegrationStatus::Disabled,
            'settings' => [],
        ]);

        $this->actingAsTenantUser()
            ->post(route('integration.calendly.toggle'))
            ->assertRedirect(route('integration.calendly'));

        $integration->refresh();
        $this->assertTrue($integration->isEnabled());
    }

    public function test_toggle_disables_integration_and_deletes_events(): void
    {
        $userId = $this->testUser->id;

        CalendlyIntegration::query()->create([
            'user_id'  => $userId,
            'status'   => IntegrationStatus::Enabled,
            'settings' => ['access_token' => 'tok'],
        ]);

        CalendlyEvent::query()->create([
            'user_id'    => $userId,
            'event_id'   => 'ev1',
            'status'     => 'active',
            'event_type' => 'consultation',
            'start_time' => now()->addHour(),
            'end_time'   => now()->addHours(2),
        ]);

        $this->actingAsTenantUser()
            ->post(route('integration.calendly.toggle'))
            ->assertRedirect(route('integration.calendly'));

        $this->assertDatabaseEmpty('calendly_events');

        $integration = CalendlyIntegration::query()->where('user_id', $userId)->first();
        $this->assertTrue($integration->status === IntegrationStatus::Disabled);
        $this->assertNull($integration->first_synced_at);
    }

    // ─── Test endpoint ────────────────────────────────────────────────────────

    public function test_test_endpoint_returns_success_for_valid_token(): void
    {
        Http::fake([
            'https://api.calendly.com/users/me' => Http::response(['resource' => []], 200),
        ]);

        $this->actingAsTenantUser()
            ->postJson(route('integration.calendly.test'), ['access_token' => 'eyJhbGciOiJIUzI1NiJ9.validtoken'])
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_test_endpoint_returns_failure_for_invalid_token(): void
    {
        Http::fake([
            'https://api.calendly.com/users/me' => Http::response([], 401),
        ]);

        $this->actingAsTenantUser()
            ->postJson(route('integration.calendly.test'), ['access_token' => 'eyJhbGciOiJIUzI1NiJ9.invalidtoken'])
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    // ─── Sync Events ─────────────────────────────────────────────────────────

    public function test_sync_events_dispatches_job(): void
    {
        Queue::fake();

        CalendlyIntegration::query()->create([
            'user_id'  => $this->testUser->id,
            'status'   => IntegrationStatus::Enabled,
            'settings' => ['access_token' => 'eyJhbGciOiJIUzI1NiJ9.tok123'],
        ]);

        $this->actingAsTenantUser()
            ->postJson(route('integration.calendly.sync'))
            ->assertOk()
            ->assertJson(['success' => true]);

        Queue::assertPushed(SyncCalendlyEventsJob::class);
    }

    public function test_sync_events_requires_access_token(): void
    {
        $this->actingAsTenantUser()
            ->postJson(route('integration.calendly.sync'))
            ->assertOk()
            ->assertJson(['success' => false]);
    }

    // ─── Webhook ─────────────────────────────────────────────────────────────

    public function test_receive_webhook_logs_payload(): void
    {
        $payload = ['event' => 'invitee.created', 'uri' => 'https://api.calendly.com/scheduled_events/xyz'];

        $this->postJson(route('calendly.webhook'), $payload)
            ->assertOk();

        $this->assertDatabaseCount('calendly_webhook_logs', 1);
        $log = CalendlyWebhookLog::query()->first();
        $this->assertFalse((bool) $log->processed);
    }

    // ─── Events listing ──────────────────────────────────────────────────────

    public function test_calendly_events_listed_on_events_tab(): void
    {
        $userId = $this->testUser->id;

        CalendlyIntegration::query()->firstOrCreate(['user_id' => $userId], [
            'status' => IntegrationStatus::Enabled, 'settings' => ['access_token' => 'tok'],
        ]);

        CalendlyEvent::query()->create([
            'user_id'    => $userId,
            'event_id'   => 'ev1',
            'status'     => 'active',
            'event_type' => 'consultation',
            'start_time' => now()->addHour(),
            'end_time'   => now()->addHours(2),
        ]);

        $this->actingAsTenantUser()
            ->get(route('integration.calendly', ['tab' => 'events']))
            ->assertOk()
            ->assertViewHas('events');
    }

    public function test_message_logs_listed_on_logs_tab(): void
    {
        $userId = $this->testUser->id;

        CalendlyIntegration::query()->firstOrCreate(['user_id' => $userId], [
            'status' => IntegrationStatus::Enabled, 'settings' => ['access_token' => 'tok'],
        ]);

        CalendlyMessageLog::query()->create([
            'user_id'          => $userId,
            'event_id'         => 'ev1',
            'recipient_type'   => 'invitee',
            'recipient_number' => '+911234567890',
            'invitee_email'    => 'x@y.com',
            'event_name'       => 'Test',
            'event_type'       => 'consultation',
            'status'           => 'sent',
            'sent_at'          => now(),
        ]);

        $this->actingAsTenantUser()
            ->get(route('integration.calendly', ['tab' => 'logs']))
            ->assertOk()
            ->assertViewHas('messageLogs');
    }

    // ─── Service layer ────────────────────────────────────────────────────────

    public function test_calendly_service_find_or_create_creates_integration(): void
    {
        $userId  = (int) $this->testUser->id;
        $service = app(CalendlyService::class);

        $integration = $service->findOrCreate($userId);

        $this->assertInstanceOf(CalendlyIntegration::class, $integration);
        $this->assertSame($userId, $integration->user_id);
    }

    public function test_calendly_service_validate_token_returns_true_for_valid(): void
    {
        Http::fake([
            'https://api.calendly.com/users/me' => Http::response(['resource' => []], 200),
        ]);

        $result = app(CalendlyService::class)->validateToken('eyJhbGciOiJIUzI1NiJ9.validtoken');
        $this->assertTrue($result);
    }

    public function test_calendly_service_validate_token_returns_false_for_invalid(): void
    {
        Http::fake([
            'https://api.calendly.com/users/me' => Http::response([], 401),
        ]);

        $result = app(CalendlyService::class)->validateToken('badtoken');
        $this->assertFalse($result);
    }

    public function test_calendly_service_toggle_deletes_events_on_disable(): void
    {
        $userId = $this->testUser->id;

        $integration = CalendlyIntegration::query()->create([
            'user_id'  => $userId,
            'status'   => IntegrationStatus::Enabled,
            'settings' => ['access_token' => 'tok'],
        ]);

        CalendlyEvent::query()->create([
            'user_id'    => $userId,
            'event_id'   => 'ev1',
            'status'     => 'active',
            'event_type' => 'consultation',
            'start_time' => now()->addHour(),
            'end_time'   => now()->addHours(2),
        ]);

        app(CalendlyService::class)->toggle($integration);

        $this->assertDatabaseEmpty('calendly_events');
        $this->assertSame(IntegrationStatus::Disabled, $integration->fresh()->status);
    }
}
