<?php

declare(strict_types=1);

namespace Tests\Feature\Alerts;

use App\Domains\Alerts\Notifications\OperationalAlertMailNotification;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class PlanExpirationAlertTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();

        config([
            'operational-alerts.enabled' => true,
            'operational-alerts.admin_emails' => ['ops@example.com'],
            'operational-alerts.admin_whatsapp_numbers' => [],
            'operational-alerts.developer_whatsapp_numbers' => [],
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_reminder_uses_central_valid_until_not_a_later_subscription_end(): void
    {
        Notification::fake();

        $validUntil = now()->addDays(7)->toDateString();
        $this->setValidUntil($validUntil);

        Subscription::query()->create([
            'plan_id' => $this->planId(),
            'status' => SubscriptionStatus::Active,
            'amount' => 1000,
            'currency' => 'INR',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addDays(90),
        ]);

        $this->artisan('alerts:plan-expiration', [
            '--tenants' => [$this->testTenant->id],
        ])->assertSuccessful();

        Notification::assertSentOnDemand(
            OperationalAlertMailNotification::class,
            function (OperationalAlertMailNotification $notification): bool {
                return ($notification->viewData['days_left'] ?? null) === 7
                    && ($notification->viewData['ends_at'] ?? null) === now()->addDays(7)->format('d M Y');
            },
        );
    }

    public function test_later_subscription_end_does_not_trigger_when_valid_until_is_not_a_reminder_day(): void
    {
        Notification::fake();

        $this->setValidUntil(now()->addDays(40)->toDateString());

        Subscription::query()->create([
            'plan_id' => $this->planId(),
            'status' => SubscriptionStatus::Active,
            'amount' => 1000,
            'currency' => 'INR',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addDays(7),
        ]);

        $this->artisan('alerts:plan-expiration', [
            '--tenants' => [$this->testTenant->id],
        ])->assertSuccessful();

        Notification::assertNothingSent();
    }

    private function planId(): int
    {
        $plan = Plan::query()->firstOrCreate(
            ['slug' => 'plan-expiration-test'],
            [
                'name' => 'Plan Expiration Test',
                'price' => 999,
                'currency' => 'INR',
                'billing_cycle' => 'monthly',
                'validity_days' => 365,
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        return (int) $plan->id;
    }

    private function setValidUntil(string $date): void
    {
        tenancy()->central(function () use ($date): void {
            $tenant = Tenant::query()->find($this->testTenant->id);
            $settings = is_array($tenant->settings) ? $tenant->settings : [];
            $settings['valid_until'] = $date;
            $tenant->settings = $settings;
            $tenant->save();
        });
    }
}
