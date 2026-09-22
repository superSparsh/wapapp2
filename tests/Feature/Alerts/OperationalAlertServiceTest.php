<?php

declare(strict_types=1);

namespace Tests\Feature\Alerts;

use App\Domains\Alerts\Services\AlertDispatcher;
use App\Domains\Alerts\Services\OperationalAlertService;
use App\Enums\NotificationContactType;
use App\Enums\OperationalAlertType;
use App\Models\AccountPreference;
use App\Models\NotificationContact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class OperationalAlertServiceTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        config(['operational-alerts.enabled' => true]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_tenant_alerts_respect_alerts_enabled_flag(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        AccountPreference::current()->update(['alerts_enabled' => false]);
        NotificationContact::query()->create([
            'user_id' => $user->id,
            'full_name' => 'Owner',
            'type' => NotificationContactType::Email,
            'contact_info' => 'owner@example.com',
        ]);

        app(OperationalAlertService::class)->notifyTenantContacts(
            type: OperationalAlertType::LowWallet,
            emailSubject: 'Low wallet',
            emailView: 'emails.alerts.low-wallet',
            emailData: ['balance' => 10, 'threshold' => 100, 'context' => 'test', 'currency' => 'INR'],
        );

        Notification::assertNothingSent();
    }

    public function test_tenant_email_alert_sends_when_enabled(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        AccountPreference::current()->update(['alerts_enabled' => true]);
        NotificationContact::query()->create([
            'user_id' => $user->id,
            'full_name' => 'Owner',
            'type' => NotificationContactType::Email,
            'contact_info' => 'owner@example.com',
        ]);

        app(OperationalAlertService::class)->notifyTenantContacts(
            type: OperationalAlertType::LowWallet,
            emailSubject: 'Low wallet',
            emailView: 'emails.alerts.low-wallet',
            emailData: ['balance' => 10, 'threshold' => 100, 'context' => 'test', 'currency' => 'INR'],
        );

        Notification::assertSentOnDemand(\App\Domains\Alerts\Notifications\OperationalAlertMailNotification::class);
    }

    public function test_low_wallet_dispatcher_notifies_contacts(): void
    {
        Notification::fake();
        AccountPreference::current()->update(['alerts_enabled' => true]);
        $user = User::factory()->create();
        NotificationContact::query()->create([
            'user_id' => $user->id,
            'full_name' => 'Owner',
            'type' => NotificationContactType::Email,
            'contact_info' => 'wallet@example.com',
        ]);

        app(\App\Domains\Billing\Services\WalletService::class)->account()->update(['balance' => 5]);
        config(['operational-alerts.low_wallet.threshold' => 100]);
        config(['operational-alerts.low_wallet.notify_developers' => false]);

        app(AlertDispatcher::class)->lowWallet(context: 'unit_test');

        Notification::assertSentOnDemand(\App\Domains\Alerts\Notifications\OperationalAlertMailNotification::class);
    }
}
