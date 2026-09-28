<?php

declare(strict_types=1);

namespace Tests\Unit\Drip;

use App\Domains\Audience\Enums\ContactStatus;
use App\Domains\Drip\Support\DripSchedule;
use App\Enums\ContactOptInStatus;
use App\Models\Contact;
use App\Models\DripCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class DripScheduleTest extends TestCase
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

    public function test_weekly_recurring_matches_days_of_week_and_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-21 09:00:00', 'Asia/Kolkata')); // Monday

        $campaign = DripCampaign::factory()->create([
            'timezone' => 'Asia/Kolkata',
            'trigger_type' => 'weekly-recurring',
            'trigger_options' => [
                'days_of_week' => [1],
                'at' => '09:00',
            ],
        ]);

        $contact = Contact::factory()->create();
        $schedule = app(DripSchedule::class);

        $this->assertSame('week-2026-09-21', $schedule->enrollmentKey($campaign, $contact));

        // Catch-up window: still due later the same day after the clock time.
        Carbon::setTestNow(Carbon::parse('2026-09-21 09:01:00', 'Asia/Kolkata'));
        $this->assertSame('week-2026-09-21', $schedule->enrollmentKey($campaign, $contact));

        // Before the scheduled clock time - not due yet.
        Carbon::setTestNow(Carbon::parse('2026-09-21 08:59:00', 'Asia/Kolkata'));
        $this->assertNull($schedule->enrollmentKey($campaign, $contact));

        Carbon::setTestNow();
    }

    public function test_specific_date_catch_up_after_scheduled_minute(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-28 14:30:00', 'Asia/Kolkata'));

        $campaign = DripCampaign::factory()->create([
            'timezone' => 'Asia/Kolkata',
            'trigger_type' => 'specific-date',
            'trigger_options' => [
                'date' => '2026-09-28',
                'at' => '10:00',
            ],
        ]);

        $contact = Contact::factory()->create();
        $schedule = app(DripSchedule::class);

        $this->assertSame('date-2026-09-28-10-00', $schedule->enrollmentKey($campaign, $contact));
        $this->assertTrue($schedule->shouldEnrollAudienceOnActivate($campaign));

        Carbon::setTestNow(Carbon::parse('2026-09-28 09:59:00', 'Asia/Kolkata'));
        $this->assertNull($schedule->enrollmentKey($campaign, $contact));

        Carbon::setTestNow();
    }

    public function test_birthday_trigger_uses_custom_field_and_before_offset(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-14 10:00:00', 'Asia/Kolkata'));

        $campaign = DripCampaign::factory()->create([
            'timezone' => 'Asia/Kolkata',
            'trigger_type' => 'say-happy-birthday',
            'trigger_options' => [
                'field' => 'date_of_birth',
                'before' => '1 day',
                'at' => '10:00',
            ],
        ]);

        $contact = Contact::factory()->create([
            'custom_fields' => ['date_of_birth' => '1990-03-15'],
        ]);

        $this->assertSame(
            'birthday-2026',
            app(DripSchedule::class)->enrollmentKey($campaign, $contact),
        );

        Carbon::setTestNow();
    }

    public function test_subscriber_added_date_skips_first_year_join_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));

        $campaign = DripCampaign::factory()->create([
            'timezone' => 'UTC',
            'trigger_type' => 'subscriber-added-date',
            'trigger_options' => [
                'before' => '0 day',
                'at' => '09:00',
            ],
        ]);

        $newContact = Contact::factory()->create([
            'status' => ContactStatus::Subscribed,
            'opt_in_status' => ContactOptInStatus::OptedIn,
            'created_at' => Carbon::parse('2026-01-10 08:00:00', 'UTC'),
        ]);

        $oldContact = Contact::factory()->create([
            'status' => ContactStatus::Subscribed,
            'opt_in_status' => ContactOptInStatus::OptedIn,
            'created_at' => Carbon::parse('2025-01-10 08:00:00', 'UTC'),
        ]);

        $schedule = app(DripSchedule::class);
        $this->assertNull($schedule->enrollmentKey($campaign, $newContact));
        $this->assertSame('joined-2026', $schedule->enrollmentKey($campaign, $oldContact));

        Carbon::setTestNow();
    }
}
