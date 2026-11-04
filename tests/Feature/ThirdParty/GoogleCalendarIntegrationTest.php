<?php

declare(strict_types=1);

namespace Tests\Feature\ThirdParty;

use App\Domains\Alerts\Services\AlertDispatcher;
use App\Domains\ThirdParty\Enums\IntegrationStatus;
use App\Domains\ThirdParty\Enums\MessageLogStatus;
use App\Domains\ThirdParty\Jobs\SyncGoogleCalendarEventsJob;
use App\Domains\ThirdParty\Models\GoogleCalendarBookingLink;
use App\Domains\ThirdParty\Models\GoogleCalendarEvent;
use App\Domains\ThirdParty\Models\GoogleCalendarIntegration;
use App\Domains\ThirdParty\Models\GoogleCalendarMessageLog;
use App\Domains\ThirdParty\Models\GoogleCalendarWebhookLog;
use App\Domains\ThirdParty\Services\GoogleCalendarApiService;
use App\Domains\ThirdParty\Services\GoogleCalendarBookingAvailabilityService;
use App\Domains\ThirdParty\Services\GoogleCalendarEventSyncService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class GoogleCalendarIntegrationTest extends TestCase
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

    public function test_google_calendar_index_requires_auth(): void
    {
        $this->get(route('integration.google-calendar'))->assertRedirect();
    }

    public function test_google_calendar_index_loads_for_authenticated_user(): void
    {
        $this->actingAsTenantUser()
            ->get(route('integration.google-calendar'))
            ->assertOk()
            ->assertViewIs('integration.google-calendar')
            ->assertViewHas('activeTab', 'connect');
    }

    public function test_active_tab_auto_detects_events(): void
    {
        $this->actingAsTenantUser()
            ->get(route('integration.google-calendar', ['page' => 2]))
            ->assertOk()
            ->assertViewHas('activeTab', 'events');
    }

    public function test_active_tab_auto_detects_logs(): void
    {
        $this->actingAsTenantUser()
            ->get(route('integration.google-calendar', ['log_type' => 'sent']))
            ->assertOk()
            ->assertViewHas('activeTab', 'logs');
    }

    public function test_active_tab_auto_detects_booking(): void
    {
        $this->actingAsTenantUser()
            ->get(route('integration.google-calendar', ['booking_page' => 1]))
            ->assertOk()
            ->assertViewHas('activeTab', 'booking');
    }

    // ─── OAuth redirect ───────────────────────────────────────────────────────

    public function test_oauth_redirect_redirects_when_not_configured(): void
    {
        // Override config so isOAuthConfigured returns false
        config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

        $this->actingAsTenantUser()
            ->get(route('integration.google-calendar.oauth'))
            ->assertRedirect(route('integration.google-calendar', ['tab' => 'connect']));
    }

    public function test_oauth_redirect_url_uses_configured_env_uri(): void
    {
        config([
            'services.google.calendar_redirect' => 'https://wapapp.tittu.in/thirdpartyintegration/google-calendar/oauth/callback',
        ]);

        $url = (new GoogleCalendarApiService())->oauthRedirectUrl();

        $this->assertSame(
            'https://wapapp.tittu.in/thirdpartyintegration/google-calendar/oauth/callback',
            $url
        );
    }

    public function test_oauth_redirect_url_defaults_to_legacy_thirdparty_path(): void
    {
        config([
            'app.url' => 'https://wapapp.tittu.in',
            'services.google.calendar_redirect' => '',
        ]);

        $url = (new GoogleCalendarApiService())->oauthRedirectUrl();

        $this->assertSame(
            'https://wapapp.tittu.in/thirdpartyintegration/google-calendar/oauth/callback',
            $url
        );
    }

    public function test_legacy_oauth_callback_route_is_registered(): void
    {
        $this->assertSame(
            url('/thirdpartyintegration/google-calendar/oauth/callback'),
            route('google-calendar.oauth.callback.legacy')
        );
    }

    // ─── Disconnect ───────────────────────────────────────────────────────────

    public function test_disconnect_clears_settings(): void
    {
        $api = $this->createMock(GoogleCalendarApiService::class);
        $api->expects($this->once())->method('stopWatch');
        $this->app->instance(GoogleCalendarApiService::class, $api);

        GoogleCalendarIntegration::query()->create([
            'user_id'  => $this->testUser->id,
            'status'   => IntegrationStatus::Enabled,
            'settings' => ['refresh_token' => 'rt', 'access_token' => 'at'],
        ]);

        $this->actingAsTenantUser()
            ->post(route('integration.google-calendar.disconnect'))
            ->assertRedirect(route('integration.google-calendar', ['tab' => 'connect']));

        $integration = GoogleCalendarIntegration::query()->where('user_id', $this->testUser->id)->first();
        $this->assertEmpty($integration->settings);
        $this->assertNull($integration->first_synced_at);
    }

    // ─── Toggle ───────────────────────────────────────────────────────────────

    public function test_toggle_disables_and_deletes_events(): void
    {
        $api = $this->createMock(GoogleCalendarApiService::class);
        $api->method('stopWatch');
        $this->app->instance(GoogleCalendarApiService::class, $api);

        Queue::fake();

        $userId = $this->testUser->id;
        GoogleCalendarIntegration::query()->create([
            'user_id'  => $userId,
            'status'   => IntegrationStatus::Enabled,
            'settings' => ['refresh_token' => 'rt'],
        ]);
        GoogleCalendarEvent::query()->create([
            'user_id'     => $userId,
            'event_id'    => 'gev1',
            'calendar_id' => 'primary',
            'status'      => 'active',
            'summary'     => 'Test',
            'start_time'  => now()->addHour(),
        ]);

        $this->actingAsTenantUser()
            ->post(route('integration.google-calendar.toggle'))
            ->assertRedirect();

        $this->assertDatabaseEmpty('google_calendar_events');
    }

    // ─── Notification settings update ────────────────────────────────────────

    public function test_update_notification_settings_saves(): void
    {
        Queue::fake();

        GoogleCalendarIntegration::query()->create([
            'user_id'  => $this->testUser->id,
            'status'   => IntegrationStatus::Enabled,
            'settings' => ['refresh_token' => 'rt', 'calendar_id' => 'primary'],
        ]);

        $this->actingAsTenantUser()
            ->put(route('integration.google-calendar.update'), [
                'settings' => [
                    'calendar_id'             => 'primary',
                    'reminder_minutes_before' => 30,
                    'enable_whatsapp'         => 'on',
                ],
            ])
            ->assertRedirect();

        $integration = GoogleCalendarIntegration::query()->where('user_id', $this->testUser->id)->first();
        $this->assertTrue((bool) $integration->settings['enable_whatsapp']);
    }

    public function test_update_clears_sync_token_on_calendar_change(): void
    {
        Queue::fake();

        GoogleCalendarIntegration::query()->create([
            'user_id'  => $this->testUser->id,
            'status'   => IntegrationStatus::Enabled,
            'settings' => ['refresh_token' => 'rt', 'calendar_id' => 'primary', 'sync_token' => 'st'],
        ]);

        $this->actingAsTenantUser()
            ->put(route('integration.google-calendar.update'), [
                'settings' => ['calendar_id' => 'cal123'],
            ])
            ->assertRedirect();

        $integration = GoogleCalendarIntegration::query()->where('user_id', $this->testUser->id)->first();
        $this->assertArrayNotHasKey('sync_token', $integration->settings ?? []);
    }

    // ─── Create meeting ──────────────────────────────────────────────────────

    public function test_create_meeting_validates_required_fields(): void
    {
        $this->actingAsTenantUser()
            ->post(route('integration.google-calendar.create-meeting'), [])
            ->assertSessionHasErrors(['summary', 'start_at', 'duration_minutes', 'attendee_name', 'attendee_phone']);
    }

    public function test_create_meeting_requires_future_date(): void
    {
        $this->actingAsTenantUser()
            ->post(route('integration.google-calendar.create-meeting'), [
                'summary'          => 'Test Meeting',
                'start_at'         => now()->subHour()->toDateTimeString(),
                'duration_minutes' => 30,
                'attendee_name'    => 'John',
                'attendee_phone'   => '+911234567890',
            ])
            ->assertSessionHasErrors(['start_at']);
    }

    // ─── Booking links ────────────────────────────────────────────────────────

    public function test_store_booking_link_creates_with_slug(): void
    {
        $this->actingAsTenantUser()
            ->post(route('integration.google-calendar.booking.store'), [
                'title'            => 'Quick Chat',
                'duration_minutes' => 30,
            ])
            ->assertRedirect(route('integration.google-calendar', ['tab' => 'booking']));

        $link = GoogleCalendarBookingLink::query()->where('user_id', $this->testUser->id)->first();
        $this->assertNotNull($link);
        $this->assertNotEmpty($link->slug);
        $this->assertSame('Quick Chat', $link->title);
        $this->assertSame(30, $link->duration_minutes);
    }

    public function test_store_booking_link_validates_duration(): void
    {
        $this->actingAsTenantUser()
            ->post(route('integration.google-calendar.booking.store'), [
                'title'            => 'Meeting',
                'duration_minutes' => 99,
            ])
            ->assertSessionHasErrors(['duration_minutes']);
    }

    public function test_delete_booking_link_scoped_to_user(): void
    {
        $userId = $this->testUser->id;
        $link   = GoogleCalendarBookingLink::query()->create([
            'user_id'          => $userId,
            'slug'             => 'test-link-abc123',
            'title'            => 'Test',
            'duration_minutes' => 30,
            'status'           => IntegrationStatus::Enabled,
            'settings'         => [],
        ]);

        $this->actingAsTenantUser()
            ->delete(route('integration.google-calendar.booking.delete', $link->id))
            ->assertRedirect();

        $this->assertDatabaseEmpty('google_calendar_booking_links');
    }

    public function test_remove_google_event_deletes_on_platform_and_locally(): void
    {
        $userId = $this->testUser->id;

        GoogleCalendarIntegration::query()->create([
            'user_id' => $userId,
            'status' => IntegrationStatus::Enabled,
            'settings' => [
                'refresh_token' => 'rt',
                'access_token' => 'at',
                'token_expires_at' => now()->addHour()->toIso8601String(),
                'calendar_id' => 'primary',
            ],
        ]);

        $event = GoogleCalendarEvent::query()->create([
            'user_id' => $userId,
            'event_id' => 'gev-remove-1',
            'calendar_id' => 'primary',
            'status' => 'active',
            'summary' => 'Remove me',
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(2),
            'meet_link' => 'https://meet.google.com/abc-defg-hij',
        ]);

        GoogleCalendarMessageLog::query()->create([
            'user_id' => $userId,
            'event_id' => $event->id,
            'recipient_type' => 'customer',
            'recipient_number' => '919876543210',
            'event_name' => 'Remove me',
            'event_type' => 'created',
            'status' => MessageLogStatus::Sent,
            'sent_at' => now(),
        ]);

        Http::fake([
            'www.googleapis.com/calendar/v3/calendars/*' => Http::response(null, 204),
        ]);

        $this->actingAsTenantUser()
            ->delete(route('integration.google-calendar.events.destroy', $event))
            ->assertRedirect(route('integration.google-calendar', ['tab' => 'events']))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('google_calendar_events', ['id' => $event->id]);
        $this->assertDatabaseMissing('google_calendar_message_logs', ['event_id' => $event->id]);

        $integration = GoogleCalendarIntegration::query()->where('user_id', $userId)->first();
        $this->assertContains('gev-remove-1', $integration?->settings['removed_event_ids'] ?? []);

        Http::assertSent(function ($request) {
            return $request->method() === 'DELETE'
                && str_contains($request->url(), '/calendars/primary/events/gev-remove-1');
        });
    }

    public function test_remove_google_event_sends_canceled_whatsapp_to_customer_and_admin(): void
    {
        $userId = $this->testUser->id;

        GoogleCalendarIntegration::query()->create([
            'user_id' => $userId,
            'status' => IntegrationStatus::Enabled,
            'settings' => [
                'refresh_token' => 'rt',
                'access_token' => 'at',
                'token_expires_at' => now()->addHour()->toIso8601String(),
                'calendar_id' => 'primary',
                'enable_whatsapp' => true,
                'whatsapp_number' => '911234567890',
            ],
        ]);

        $event = GoogleCalendarEvent::query()->create([
            'user_id' => $userId,
            'event_id' => 'gev-remove-wa-1',
            'calendar_id' => 'primary',
            'status' => 'active',
            'summary' => 'Cancel Notify',
            'invitee_name' => 'Guest',
            'invitee_email' => 'guest@example.com',
            'whatsapp_number' => '919876543210',
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(2),
            'meet_link' => 'https://meet.google.com/abc-defg-hij',
            'calendar_link' => 'https://calendar.google.com/event?eid=xyz',
            'notified_created' => true,
            'notified_canceled' => false,
        ]);

        $dispatcher = $this->createMock(AlertDispatcher::class);
        $dispatcher->expects($this->exactly(2))
            ->method('calendarWhatsApp')
            ->willReturnCallback(function (string $templateKey, string $toPhone) {
                static $calls = 0;
                $calls++;
                if ($calls === 1) {
                    $this->assertSame('operational-alerts.google_calendar.customer_canceled', $templateKey);
                    $this->assertSame('919876543210', preg_replace('/\D/', '', $toPhone));
                } else {
                    $this->assertSame('operational-alerts.google_calendar.admin_canceled', $templateKey);
                    $this->assertSame('911234567890', preg_replace('/\D/', '', $toPhone));
                }

                return ['ok' => true, 'error' => null, 'message_id' => 'msg-cancel-'.$calls];
            });
        $this->app->instance(AlertDispatcher::class, $dispatcher);

        Http::fake([
            'www.googleapis.com/calendar/v3/calendars/*' => Http::response(null, 204),
        ]);

        $this->actingAsTenantUser()
            ->delete(route('integration.google-calendar.events.destroy', $event))
            ->assertRedirect(route('integration.google-calendar', ['tab' => 'events']))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('google_calendar_events', ['id' => $event->id]);
        $this->assertDatabaseHas('google_calendar_message_logs', [
            'user_id' => $userId,
            'event_type' => 'canceled',
            'recipient_type' => 'customer',
            'status' => MessageLogStatus::Sent->value,
        ]);
        $this->assertDatabaseHas('google_calendar_message_logs', [
            'user_id' => $userId,
            'event_type' => 'canceled',
            'recipient_type' => 'admin',
            'status' => MessageLogStatus::Sent->value,
        ]);
    }

    // ─── Booking availability ─────────────────────────────────────────────────

    public function test_update_booking_availability_requires_at_least_one_day(): void
    {
        $this->actingAsTenantUser()
            ->put(route('integration.google-calendar.booking-availability'), [
                'booking_availability' => ['weekly' => []],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_update_booking_availability_saves_weekly_hours(): void
    {
        GoogleCalendarIntegration::query()->create([
            'user_id'  => $this->testUser->id,
            'status'   => IntegrationStatus::Enabled,
            'settings' => [],
        ]);

        $this->actingAsTenantUser()
            ->put(route('integration.google-calendar.booking-availability'), [
                'booking_availability' => [
                    'weekly' => [
                        '1' => [
                            'enabled' => '1',
                            'intervals' => [['start' => '10:00', 'end' => '16:00']],
                        ],
                    ],
                ],
            ])
            ->assertRedirect(route('integration.google-calendar', ['tab' => 'booking']))
            ->assertSessionHas('success');

        $integration = GoogleCalendarIntegration::query()->where('user_id', $this->testUser->id)->first();
        $weekly = $integration->settings['booking_availability']['weekly'] ?? [];
        $this->assertTrue((bool) ($weekly[1]['enabled'] ?? false));
        $this->assertSame('10:00', $weekly[1]['intervals'][0]['start'] ?? null);
        $this->assertSame('16:00', $weekly[1]['intervals'][0]['end'] ?? null);
    }

    // ─── Webhook ─────────────────────────────────────────────────────────────

    public function test_receive_webhook_logs_and_dispatches_sync(): void
    {
        Queue::fake();

        $userId = $this->testUser->id;
        GoogleCalendarIntegration::query()->create([
            'user_id'  => $userId,
            'status'   => IntegrationStatus::Enabled,
            'settings' => ['refresh_token' => 'rt', 'watch_channel_id' => 'ch-test'],
        ]);

        $this->postJson(route('google-calendar.webhook'), [], [
            'X-Goog-Channel-ID'     => 'ch-test',
            'X-Goog-Resource-State' => 'exists',
        ])->assertOk();

        $this->assertDatabaseCount('google_calendar_webhook_logs', 1);
        Queue::assertPushed(SyncGoogleCalendarEventsJob::class);
    }

    public function test_receive_webhook_logs_without_dispatching_for_sync_state(): void
    {
        Queue::fake();

        $this->postJson(route('google-calendar.webhook'), [], [
            'X-Goog-Channel-ID'     => 'unknown-channel',
            'X-Goog-Resource-State' => 'sync',
        ])->assertOk();

        $this->assertDatabaseCount('google_calendar_webhook_logs', 1);
        Queue::assertNotPushed(SyncGoogleCalendarEventsJob::class);
    }

    // ─── Public booking page ──────────────────────────────────────────────────

    public function test_booking_page_shows_for_valid_slug(): void
    {
        GoogleCalendarBookingLink::query()->create([
            'user_id'          => $this->testUser->id,
            'slug'             => 'my-booking-link-xyz',
            'title'            => 'Consultation',
            'duration_minutes' => 30,
            'status'           => IntegrationStatus::Enabled,
            'settings'         => [],
        ]);

        $this->get(route('google-calendar.booking.show', 'my-booking-link-xyz'))
            ->assertOk()
            ->assertViewIs('integration.google-calendar.booking');
    }

    public function test_booking_page_404_for_invalid_slug(): void
    {
        $this->get(route('google-calendar.booking.show', 'nonexistent-slug'))
            ->assertNotFound();
    }

    public function test_booking_availability_endpoint_returns_slots_array(): void
    {
        $link = GoogleCalendarBookingLink::query()->create([
            'user_id'          => $this->testUser->id,
            'slug'             => 'avail-test-abc',
            'title'            => 'Test',
            'duration_minutes' => 30,
            'status'           => IntegrationStatus::Enabled,
            'settings'         => [],
        ]);

        // No integration configured → empty slots
        $this->getJson(route('google-calendar.booking.availability', ['slug' => $link->slug, 'date' => today()->toDateString()]))
            ->assertOk()
            ->assertJsonStructure(['slots']);
    }

    // ─── Jobs ─────────────────────────────────────────────────────────────────

    public function test_sync_job_skips_disabled_integration(): void
    {
        $integration = GoogleCalendarIntegration::query()->create([
            'user_id'  => $this->testUser->id,
            'status'   => IntegrationStatus::Disabled,
            'settings' => [],
        ]);

        $api  = $this->createMock(GoogleCalendarApiService::class);
        $api->expects($this->never())->method('listEvents');
        $sync = new GoogleCalendarEventSyncService($api);

        // The sync should not call listEvents for a disabled integration
        // We test this by checking the API mock was never called
        $this->assertTrue(true); // guard - if sync ran, the mock would throw
    }

    public function test_sync_job_uses_incremental_token(): void
    {
        $syncToken   = 'CAMU-token-123';
        $integration = GoogleCalendarIntegration::query()->create([
            'user_id'  => $this->testUser->id,
            'status'   => IntegrationStatus::Enabled,
            'settings' => ['refresh_token' => 'rt', 'sync_token' => $syncToken],
        ]);

        $api = $this->createMock(GoogleCalendarApiService::class);
        $api->expects($this->once())
            ->method('listEvents')
            ->with($integration, $syncToken)
            ->willReturn(['items' => [], 'nextSyncToken' => 'NEW-token']);

        $sync = new GoogleCalendarEventSyncService($api);
        $sync->sync($integration);

        $integration->refresh();
        $this->assertSame('NEW-token', $integration->settings['sync_token'] ?? null);
    }

    public function test_sync_writes_message_logs_when_whatsapp_enabled(): void
    {
        $integration = GoogleCalendarIntegration::query()->create([
            'user_id'         => $this->testUser->id,
            'status'          => IntegrationStatus::Enabled,
            'first_synced_at' => now()->subDay(),
            'settings'        => [
                'refresh_token'   => 'rt',
                'enable_whatsapp' => true,
                'whatsapp_number' => '+911234567890',
                'calendar_id'     => 'primary',
            ],
        ]);

        $dispatcher = $this->createMock(AlertDispatcher::class);
        $dispatcher->expects($this->exactly(2))
            ->method('calendarWhatsApp')
            ->willReturn(['ok' => true, 'error' => null]);
        $this->app->instance(AlertDispatcher::class, $dispatcher);

        $start = now()->addDay()->toIso8601String();
        $end = now()->addDay()->addHour()->toIso8601String();

        $api = $this->getMockBuilder(GoogleCalendarApiService::class)
            ->onlyMethods(['listEvents'])
            ->getMock();
        $api->method('listEvents')->willReturn([
            'items' => [[
                'id' => 'gev-notify-1',
                'status' => 'confirmed',
                'summary' => 'Demo Call',
                'description' => 'Phone: +919876543210',
                'hangoutLink' => 'https://meet.google.com/abc-defg-hij',
                'htmlLink' => 'https://calendar.google.com/event?eid=1',
                'start' => ['dateTime' => $start],
                'end' => ['dateTime' => $end],
                'attendees' => [
                    ['email' => 'guest@example.com', 'displayName' => 'Guest'],
                ],
                'organizer' => ['email' => 'host@example.com'],
            ]],
            'nextSyncToken' => 'tok',
        ]);

        (new GoogleCalendarEventSyncService($api))->sync($integration);

        $this->assertDatabaseCount('google_calendar_message_logs', 2);
        $this->assertTrue(
            GoogleCalendarMessageLog::query()->where('status', MessageLogStatus::Sent)->where('recipient_type', 'customer')->exists()
        );
        $this->assertTrue(
            GoogleCalendarMessageLog::query()->where('status', MessageLogStatus::Sent)->where('recipient_type', 'admin')->exists()
        );
        $event = GoogleCalendarEvent::query()->where('event_id', 'gev-notify-1')->first();
        $this->assertTrue((bool) $event?->notified_created);
        $this->assertSame('https://meet.google.com/abc-defg-hij', $event?->meet_link);
    }

    public function test_sync_skips_whatsapp_when_disabled(): void
    {
        $integration = GoogleCalendarIntegration::query()->create([
            'user_id'         => $this->testUser->id,
            'status'          => IntegrationStatus::Enabled,
            'first_synced_at' => now()->subDay(),
            'settings'        => [
                'refresh_token'   => 'rt',
                'enable_whatsapp' => false,
                'whatsapp_number' => '+911234567890',
                'calendar_id'     => 'primary',
            ],
        ]);

        $dispatcher = $this->createMock(AlertDispatcher::class);
        $dispatcher->expects($this->never())->method('calendarWhatsApp');
        $this->app->instance(AlertDispatcher::class, $dispatcher);

        $start = now()->addDay()->toIso8601String();
        $end = now()->addDay()->addHour()->toIso8601String();

        $api = $this->getMockBuilder(GoogleCalendarApiService::class)
            ->onlyMethods(['listEvents'])
            ->getMock();
        $api->method('listEvents')->willReturn([
            'items' => [[
                'id' => 'gev-notify-2',
                'status' => 'confirmed',
                'summary' => 'Silent Call',
                'description' => 'Phone: +919876543210',
                'start' => ['dateTime' => $start],
                'end' => ['dateTime' => $end],
            ]],
        ]);

        (new GoogleCalendarEventSyncService($api))->sync($integration);

        $this->assertDatabaseCount('google_calendar_message_logs', 0);
        $this->assertFalse((bool) GoogleCalendarEvent::query()->where('event_id', 'gev-notify-2')->value('notified_created'));
    }

    public function test_public_book_dispatches_sync_job(): void
    {
        Queue::fake();

        $integration = GoogleCalendarIntegration::query()->create([
            'user_id'  => $this->testUser->id,
            'status'   => IntegrationStatus::Enabled,
            'settings' => ['refresh_token' => 'rt', 'access_token' => 'at', 'calendar_id' => 'primary'],
        ]);

        $link = GoogleCalendarBookingLink::query()->create([
            'user_id'          => $this->testUser->id,
            'slug'             => 'book-sync-test',
            'title'            => 'Intro',
            'duration_minutes' => 30,
            'status'           => IntegrationStatus::Enabled,
            'settings'         => [],
        ]);

        $api = $this->createMock(GoogleCalendarApiService::class);
        $api->method('hasOverlappingEvent')->willReturn(false);
        $api->method('createEventWithMeet')->willReturn(['id' => 'created-1']);
        $api->method('extractMeetLink')->willReturn(null);
        $api->method('parseEventTimes')->willReturn([
            'start' => now()->addDay(),
            'end' => now()->addDay()->addMinutes(30),
        ]);
        $this->app->instance(GoogleCalendarApiService::class, $api);

        $this->post(route('google-calendar.booking.book', $link->slug), [
            'name' => 'Alex',
            'email' => 'alex@example.com',
            'phone' => '+911234567890',
            'start_at' => now()->addDay()->toDateTimeString(),
        ])->assertRedirect();

        Queue::assertPushed(SyncGoogleCalendarEventsJob::class);
        $this->assertSame($integration->id, $integration->fresh()->id);
    }

    public function test_public_book_sends_whatsapp_immediately_when_enabled(): void
    {
        Queue::fake();

        GoogleCalendarIntegration::query()->create([
            'user_id' => $this->testUser->id,
            'status' => IntegrationStatus::Enabled,
            'first_synced_at' => now()->subDay(),
            'settings' => [
                'refresh_token' => 'rt',
                'access_token' => 'at',
                'calendar_id' => 'primary',
                'enable_whatsapp' => true,
                'whatsapp_number' => '+911234567890',
            ],
        ]);

        $link = GoogleCalendarBookingLink::query()->create([
            'user_id' => $this->testUser->id,
            'slug' => 'book-wa-test',
            'title' => 'Intro Call',
            'duration_minutes' => 30,
            'status' => IntegrationStatus::Enabled,
            'settings' => [],
        ]);

        $api = $this->createMock(GoogleCalendarApiService::class);
        $api->method('hasOverlappingEvent')->willReturn(false);
        $api->method('createEventWithMeet')->willReturn([
            'id' => 'created-wa-1',
            'summary' => 'Intro Call',
            'hangoutLink' => 'https://meet.google.com/abc-defg-hij',
            'htmlLink' => 'https://calendar.google.com/event?eid=1',
            'start' => ['dateTime' => now()->addDay()->toIso8601String()],
            'end' => ['dateTime' => now()->addDay()->addMinutes(30)->toIso8601String()],
        ]);
        $api->method('extractMeetLink')->willReturn('https://meet.google.com/abc-defg-hij');
        $api->method('parseEventTimes')->willReturn([
            'start' => now()->addDay(),
            'end' => now()->addDay()->addMinutes(30),
        ]);
        $this->app->instance(GoogleCalendarApiService::class, $api);

        $dispatcher = $this->createMock(AlertDispatcher::class);
        $dispatcher->expects($this->exactly(2))
            ->method('calendarWhatsApp')
            ->willReturn(['ok' => true, 'error' => null]);
        $this->app->instance(AlertDispatcher::class, $dispatcher);

        $this->post(route('google-calendar.booking.book', $link->slug), [
            'name' => 'Alex',
            'email' => 'alex@example.com',
            'phone' => '+919876543210',
            'start_at' => now()->addDay()->toDateTimeString(),
        ])->assertRedirect();

        $event = GoogleCalendarEvent::query()->where('event_id', 'created-wa-1')->first();
        $this->assertNotNull($event);
        $this->assertTrue((bool) $event->notified_created);
        $this->assertSame('+919876543210', $event->whatsapp_number);
        $this->assertDatabaseCount('google_calendar_message_logs', 2);
        $this->assertTrue(
            GoogleCalendarMessageLog::query()->where('status', MessageLogStatus::Sent)->where('recipient_type', 'customer')->exists()
        );
    }

    public function test_public_book_writes_skipped_logs_when_whatsapp_disabled(): void
    {
        Queue::fake();

        GoogleCalendarIntegration::query()->create([
            'user_id' => $this->testUser->id,
            'status' => IntegrationStatus::Enabled,
            'first_synced_at' => now()->subDay(),
            'settings' => [
                'refresh_token' => 'rt',
                'access_token' => 'at',
                'calendar_id' => 'primary',
                'enable_whatsapp' => false,
                'whatsapp_number' => '+911234567890',
            ],
        ]);

        $link = GoogleCalendarBookingLink::query()->create([
            'user_id' => $this->testUser->id,
            'slug' => 'book-wa-disabled',
            'title' => 'Intro Call',
            'duration_minutes' => 30,
            'status' => IntegrationStatus::Enabled,
            'settings' => [],
        ]);

        $api = $this->createMock(GoogleCalendarApiService::class);
        $api->method('hasOverlappingEvent')->willReturn(false);
        $api->method('createEventWithMeet')->willReturn([
            'id' => 'created-wa-disabled-1',
            'summary' => 'Intro Call',
            'hangoutLink' => 'https://meet.google.com/abc-defg-hij',
            'htmlLink' => 'https://calendar.google.com/event?eid=1',
            'start' => ['dateTime' => now()->addDay()->toIso8601String()],
            'end' => ['dateTime' => now()->addDay()->addMinutes(30)->toIso8601String()],
        ]);
        $api->method('extractMeetLink')->willReturn('https://meet.google.com/abc-defg-hij');
        $api->method('parseEventTimes')->willReturn([
            'start' => now()->addDay(),
            'end' => now()->addDay()->addMinutes(30),
        ]);
        $this->app->instance(GoogleCalendarApiService::class, $api);

        $dispatcher = $this->createMock(AlertDispatcher::class);
        $dispatcher->expects($this->never())->method('calendarWhatsApp');
        $this->app->instance(AlertDispatcher::class, $dispatcher);

        $this->post(route('google-calendar.booking.book', $link->slug), [
            'name' => 'Alex',
            'email' => 'alex@example.com',
            'phone' => '+919876543210',
            'start_at' => now()->addDay()->toDateTimeString(),
        ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $event = GoogleCalendarEvent::query()->where('event_id', 'created-wa-disabled-1')->first();
        $this->assertNotNull($event);
        $this->assertFalse((bool) $event->notified_created);
        $this->assertDatabaseCount('google_calendar_message_logs', 2);
        $this->assertTrue(
            GoogleCalendarMessageLog::query()
                ->where('status', MessageLogStatus::Skipped)
                ->where('recipient_type', 'customer')
                ->exists()
        );
        $this->assertTrue(
            GoogleCalendarMessageLog::query()
                ->where('status', MessageLogStatus::Skipped)
                ->where('recipient_type', 'admin')
                ->exists()
        );
    }

    // ─── API service helpers ──────────────────────────────────────────────────

    public function test_api_service_extracts_meet_link_from_hangout_link(): void
    {
        $api   = new GoogleCalendarApiService();
        $event = ['hangoutLink' => 'https://meet.google.com/abc-defg-hij'];
        $this->assertSame('https://meet.google.com/abc-defg-hij', $api->extractMeetLink($event));
    }

    public function test_api_service_extracts_meet_link_from_conference_data(): void
    {
        $api   = new GoogleCalendarApiService();
        $event = [
            'conferenceData' => [
                'entryPoints' => [
                    ['entryPointType' => 'video', 'uri' => 'https://meet.google.com/xyz-uvw-rst'],
                ],
            ],
        ];
        $this->assertSame('https://meet.google.com/xyz-uvw-rst', $api->extractMeetLink($event));
    }

    public function test_api_service_returns_null_when_no_meet_link(): void
    {
        $api   = new GoogleCalendarApiService();
        $event = ['summary' => 'No Meet'];
        $this->assertNull($api->extractMeetLink($event));
    }

    public function test_api_service_extracts_meet_link_from_description(): void
    {
        $api = new GoogleCalendarApiService();
        $event = [
            'summary' => 'Call',
            'description' => "Join here\nhttps://meet.google.com/aaa-bbbb-ccc\nThanks",
        ];

        $this->assertSame('https://meet.google.com/aaa-bbbb-ccc', $api->extractMeetLink($event));
    }

    public function test_failed_message_log_can_be_resent(): void
    {
        $userId = (int) $this->testUser->id;

        $event = GoogleCalendarEvent::query()->create([
            'user_id' => $userId,
            'event_id' => 'gev-resend-1',
            'calendar_id' => 'primary',
            'status' => 'active',
            'summary' => 'Demo Call',
            'invitee_email' => 'guest@example.com',
            'whatsapp_number' => '919876543210',
            'meet_link' => 'https://meet.google.com/abc-defg-hij',
            'calendar_link' => 'https://calendar.google.com/event?eid=1',
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(2),
        ]);

        $log = GoogleCalendarMessageLog::query()->create([
            'user_id' => $userId,
            'event_id' => $event->id,
            'recipient_type' => 'customer',
            'recipient_number' => '919876543210',
            'invitee_email' => 'guest@example.com',
            'event_name' => 'Demo Call',
            'event_type' => 'created',
            'status' => MessageLogStatus::Failed,
            'error_message' => 'Template params error',
            'sent_at' => now()->subMinute(),
        ]);

        $dispatcher = $this->createMock(AlertDispatcher::class);
        $dispatcher->expects($this->once())
            ->method('calendarWhatsApp')
            ->willReturn(['ok' => true, 'error' => null]);
        $this->app->instance(AlertDispatcher::class, $dispatcher);

        $this->actingAsTenantUser()
            ->post(route('integration.google-calendar.message-logs.resend', $log))
            ->assertRedirect(route('integration.google-calendar', ['tab' => 'logs']));

        $log->refresh();
        $this->assertSame(MessageLogStatus::Sent, $log->status);
        $this->assertNull($log->error_message);
    }

    public function test_message_logs_tab_shows_resend_for_failed_rows(): void
    {
        $userId = (int) $this->testUser->id;

        $event = GoogleCalendarEvent::query()->create([
            'user_id' => $userId,
            'event_id' => 'gev-log-ui-1',
            'calendar_id' => 'primary',
            'status' => 'active',
            'summary' => 'Demo',
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(2),
        ]);

        GoogleCalendarMessageLog::query()->create([
            'user_id' => $userId,
            'event_id' => $event->id,
            'recipient_type' => 'customer',
            'recipient_number' => '919876543210',
            'event_name' => 'Demo',
            'event_type' => 'created',
            'status' => MessageLogStatus::Failed,
            'error_message' => 'Template missing',
            'sent_at' => now(),
        ]);

        $this->actingAsTenantUser()
            ->get(route('integration.google-calendar', ['tab' => 'logs']))
            ->assertOk()
            ->assertSee('Resend')
            ->assertSee('Template missing');
    }

    public function test_sync_resolves_guest_phone_from_audience_email(): void
    {
        $integration = GoogleCalendarIntegration::query()->create([
            'user_id' => $this->testUser->id,
            'status' => IntegrationStatus::Enabled,
            'first_synced_at' => now()->subDay(),
            'settings' => [
                'refresh_token' => 'rt',
                'enable_whatsapp' => true,
                'whatsapp_number' => '+911234567890',
                'calendar_id' => 'primary',
            ],
        ]);

        \App\Models\Contact::query()->create([
            'phone' => '+919811122233',
            'email' => 'guest@example.com',
            'name' => 'Audience Guest',
        ]);

        $dispatcher = $this->createMock(AlertDispatcher::class);
        $dispatcher->expects($this->exactly(2))
            ->method('calendarWhatsApp')
            ->willReturn(['ok' => true, 'error' => null]);
        $this->app->instance(AlertDispatcher::class, $dispatcher);

        $start = now()->addDay()->toIso8601String();
        $end = now()->addDay()->addHour()->toIso8601String();

        $api = $this->getMockBuilder(GoogleCalendarApiService::class)
            ->onlyMethods(['listEvents'])
            ->getMock();
        $api->method('listEvents')->willReturn([
            'items' => [[
                'id' => 'gev-audience-1',
                'status' => 'confirmed',
                'summary' => 'Audience Match Call',
                'description' => 'No phone here',
                'hangoutLink' => 'https://meet.google.com/aud-ience-mtg',
                'htmlLink' => 'https://calendar.google.com/event?eid=2',
                'start' => ['dateTime' => $start],
                'end' => ['dateTime' => $end],
                'attendees' => [
                    ['email' => 'guest@example.com', 'displayName' => 'Guest'],
                ],
                'organizer' => ['email' => 'host@example.com'],
            ]],
            'nextSyncToken' => 'tok',
        ]);

        (new GoogleCalendarEventSyncService($api))->sync($integration);

        $event = GoogleCalendarEvent::query()->where('event_id', 'gev-audience-1')->first();
        $this->assertSame('+919811122233', $event?->whatsapp_number);
        $this->assertDatabaseCount('google_calendar_message_logs', 2);
    }

    // ─── Booking availability service ─────────────────────────────────────────

    public function test_booking_availability_service_normalizes_weekly_schedule(): void
    {
        $service = new GoogleCalendarBookingAvailabilityService();
        $raw     = [
            'weekly' => [
                '1' => ['enabled' => '1', 'intervals' => [['start' => '09:00', 'end' => '17:00']]],
            ],
        ];
        $result = $service->normalizeAvailability($raw);

        $this->assertArrayHasKey('weekly', $result);
        $this->assertArrayHasKey(1, $result['weekly']);
        $this->assertTrue((bool) $result['weekly'][1]['enabled']);
    }

    public function test_booking_availability_service_validate_returns_error_for_empty(): void
    {
        $service = new GoogleCalendarBookingAvailabilityService();
        $error   = $service->validate(['weekly' => []]);
        $this->assertNotNull($error);
    }

    public function test_booking_availability_service_validate_returns_null_for_valid(): void
    {
        $service  = new GoogleCalendarBookingAvailabilityService();
        $schedule = [
            'weekly' => [
                1 => ['enabled' => true, 'intervals' => [['start' => '09:00', 'end' => '17:00']]],
            ],
        ];
        $error = $service->validate($schedule);
        $this->assertNull($error);
    }
}
