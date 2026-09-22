<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Services;

use App\Domains\Billing\Services\WalletService;
use App\Enums\OperationalAlertType;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WhatsappLine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AlertDispatcher
{
    public function __construct(
        private readonly OperationalAlertService $alerts,
        private readonly PlatformAlertService $platform,
        private readonly WalletService $wallet,
    ) {}

    public function planExpiration(Subscription $subscription, int $daysLeft, object $plan, ?User $user = null): void
    {
        $meta = is_array($subscription->metadata) ? $subscription->metadata : [];
        $sent = (array) ($meta['plan_expiration_alerts_sent'] ?? []);
        $dayKey = (string) $daysLeft;
        if (in_array($dayKey, $sent, true)) {
            return;
        }

        $support = (array) config('operational-alerts.support', []);
        $company = tenant('company_name') ?? tenant('name');
        $userDetails = (object) [
            'first_name' => $user?->name ? explode(' ', (string) $user->name)[0] : 'Customer',
            'last_name' => '',
            'email' => $user?->email,
        ];

        $emailData = [
            'user_details' => $userDetails,
            'subscription' => $subscription,
            'plan_details' => $plan,
            'company_name' => $company,
            'support_phone' => $support['phone'] ?? '',
            'support_whatsapp_url' => $support['whatsapp_url'] ?? '',
            'support_email' => $support['email'] ?? '',
            'days_left' => $daysLeft,
        ];

        $template = $daysLeft <= 3
            ? (string) config('operational-alerts.plan_expiration.whatsapp_template')
            : (string) config('operational-alerts.plan_expiration.whatsapp_renewal_template');

        $this->alerts->notifyTenantContacts(
            type: OperationalAlertType::PlanExpiration,
            emailSubject: "Your plan expires in {$daysLeft} day(s)",
            emailView: 'emails.plan-expiration',
            emailData: $emailData,
            whatsappTemplate: $template,
            whatsappParams: [
                'customer_name' => (string) ($user?->name ?? 'Customer'),
                'plan_name' => (string) ($plan->name ?? 'Plan'),
                'days_left' => $daysLeft,
                'ends_at' => $subscription->ends_at?->format('d M Y') ?? '',
            ],
            owner: $user,
        );

        $this->platform->notifyAdmins(
            OperationalAlertType::PlanExpiration,
            "Plan expiring in {$daysLeft} day(s) — ".($company ?? 'tenant'),
            'emails.plan-expiration-admin',
            $emailData,
        );

        $sent[] = $dayKey;
        $subscription->update([
            'metadata' => array_merge($meta, ['plan_expiration_alerts_sent' => array_values(array_unique($sent))]),
        ]);
    }

    public function inboxNewMessage(string $fromPhone, string $preview, ?string $contactName = null): void
    {
        $throttle = max(30, (int) config('operational-alerts.inbox_new_message.throttle_seconds', 120));
        $tenantId = tenant('id') ?? 'unknown';
        $cacheKey = "ops-inbox-alert:{$tenantId}:".md5($fromPhone);
        if (! Cache::add($cacheKey, 1, $throttle)) {
            return;
        }

        $this->alerts->notifyTenantContacts(
            type: OperationalAlertType::InboxNewMessage,
            emailSubject: 'New WhatsApp message received',
            emailView: 'emails.alerts.inbox-new-message',
            emailData: [
                'from_phone' => $fromPhone,
                'contact_name' => $contactName,
                'preview' => mb_substr($preview, 0, 280),
                'received_at' => now()->format('d M Y h:i A'),
            ],
            whatsappTemplate: (string) config('operational-alerts.inbox_new_message.whatsapp_template'),
            whatsappParams: [
                'from' => $fromPhone,
                'name' => $contactName ?: $fromPhone,
                'message' => mb_substr($preview, 0, 200),
            ],
        );
    }

    public function lowWallet(?float $balance = null, string $context = 'general'): void
    {
        $balance ??= $this->wallet->balance();
        $threshold = (float) config('operational-alerts.low_wallet.threshold', 100);

        if ($balance > $threshold) {
            return;
        }

        $tenantId = tenant('id') ?? 'unknown';
        $hours = max(1, (int) config('operational-alerts.low_wallet.throttle_hours', 12));
        $cacheKey = "ops-low-wallet:{$tenantId}:{$context}";
        if (! Cache::add($cacheKey, 1, now()->addHours($hours))) {
            return;
        }

        $this->alerts->notifyTenantContacts(
            type: OperationalAlertType::LowWallet,
            emailSubject: 'Low wallet balance — please recharge',
            emailView: 'emails.alerts.low-wallet',
            emailData: [
                'balance' => $balance,
                'threshold' => $threshold,
                'context' => $context,
                'currency' => WalletAccount::query()->value('currency') ?? 'INR',
            ],
            whatsappTemplate: (string) config('operational-alerts.low_wallet.whatsapp_template'),
            whatsappParams: [
                'balance' => number_format($balance, 2, '.', ''),
                'threshold' => number_format($threshold, 2, '.', ''),
                'context' => $context,
            ],
        );

        if ((bool) config('operational-alerts.low_wallet.notify_developers', true)) {
            $this->platform->notifyDeveloperError(
                'LowWallet',
                "Tenant {$tenantId} balance {$balance} (threshold {$threshold}) during {$context}",
                throttleKey: $cacheKey.'-dev',
                throttleSeconds: $hours * 3600,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function walletCreditRequested(array $payload): void
    {
        $emails = array_filter([
            $payload['customer_email'] ?? null,
            ...((array) config('operational-alerts.admin_emails', [])),
        ]);

        $this->alerts->notifyEmails(
            $emails,
            OperationalAlertType::WalletCreditRequested,
            'Wallet credit request received',
            'emails.alerts.wallet-credit-requested',
            $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function walletCreditCompleted(array $payload): void
    {
        $emails = array_filter([
            $payload['customer_email'] ?? null,
            ...((array) config('operational-alerts.admin_emails', [])),
        ]);

        $this->alerts->notifyEmails(
            $emails,
            OperationalAlertType::WalletCreditCompleted,
            'Wallet credit completed',
            'emails.alerts.wallet-credit-completed',
            $payload,
        );
    }

    public function phoneQualityChanged(WhatsappLine $line, ?string $oldQuality, ?string $oldTier): void
    {
        $this->alerts->notifyTenantContacts(
            type: OperationalAlertType::PhoneQualityChanged,
            emailSubject: 'WhatsApp phone quality / messaging limit update',
            emailView: 'emails.alerts.phone-quality-changed',
            emailData: [
                'phone' => $line->phone,
                'display_name' => $line->display_name,
                'old_quality' => $oldQuality,
                'new_quality' => $line->quality_rating,
                'old_tier' => $oldTier,
                'new_tier' => $line->messaging_limit_tier,
            ],
            whatsappTemplate: (string) config('operational-alerts.phone_quality.whatsapp_template'),
            whatsappParams: [
                'phone' => (string) $line->phone,
                'quality' => (string) ($line->quality_rating ?? 'N/A'),
                'tier' => (string) ($line->messaging_limit_tier ?? 'N/A'),
            ],
        );
    }

    public function accountPurged(string $summary, ?string $customerEmail = null): void
    {
        $emails = array_filter([
            $customerEmail,
            ...((array) config('operational-alerts.admin_emails', [])),
        ]);

        $this->alerts->notifyEmails(
            $emails,
            OperationalAlertType::AccountPurged,
            'Account data deletion completed',
            'emails.alerts.account-purged',
            ['summary' => $summary, 'completed_at' => now()->format('d M Y h:i A')],
        );
    }

    /**
     * @param  array<string, mixed>  $submission
     */
    public function readinessSubmitted(array $submission, string $status = 'submitted'): void
    {
        $type = match ($status) {
            'eligible' => OperationalAlertType::ReadinessEligible,
            'not_eligible' => OperationalAlertType::ReadinessNotEligible,
            default => OperationalAlertType::ReadinessSubmitted,
        };

        $view = match ($status) {
            'eligible' => 'emails.alerts.readiness-eligible',
            'not_eligible' => 'emails.alerts.readiness-not-eligible',
            default => 'emails.alerts.readiness-submitted',
        };

        $adminEmails = (array) config('operational-alerts.readiness.admin_emails', []);
        if ($adminEmails === []) {
            $adminEmails = (array) config('operational-alerts.admin_emails', []);
        }

        $this->platform->notifyAdmins($type, 'Customer readiness: '.$status, $view, $submission, $adminEmails);

        if (! empty($submission['customer_email'])) {
            $this->alerts->notifyEmails(
                [(string) $submission['customer_email']],
                $type,
                'We received your readiness submission',
                $view,
                $submission,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $bill
     */
    public function cloudBillUploaded(array $bill): void
    {
        $this->platform->notifyAdmins(
            OperationalAlertType::CloudBillUploaded,
            'Cloud bill uploaded: '.($bill['filename'] ?? 'file'),
            'emails.alerts.cloud-bill',
            $bill,
        );
    }

    /**
     * @param  array<string, mixed>  $digest
     */
    public function whatsappHealthDigest(array $digest): void
    {
        $emails = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) config('services.wa_health.digest_emails', ''))
        )));

        $this->platform->notifyAdmins(
            OperationalAlertType::WhatsappHealthDigest,
            'WhatsApp Health Digest — '.now()->format('d M Y'),
            'emails.alerts.wa-health-digest',
            $digest,
            $emails !== [] ? $emails : null,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function accountExpirationReport(array $rows): void
    {
        $emails = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) (config('services.account_expiration.report_emails') ?: ''))
        )));

        $this->platform->notifyAdmins(
            OperationalAlertType::AccountExpirationReport,
            'Monthly account expiration report — '.now()->format('F Y'),
            'emails.alerts.account-expiration-report',
            ['rows' => $rows, 'generated_at' => now()->format('d M Y h:i A')],
            $emails !== [] ? $emails : null,
        );
    }

    /**
     * @param  array<string, string|int|float>  $params
     */
    public function calendarWhatsApp(string $templateKey, string $toPhone, array $params): void
    {
        $template = (string) config($templateKey, '');
        if ($template === '' || $toPhone === '') {
            return;
        }

        $this->alerts->notifyWhatsAppNumbers([$toPhone], $template, $params);
    }

    public function ensureActiveSubscriptionForPlanAlerts(): ?Subscription
    {
        return Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->whereNotNull('ends_at')
            ->orderByDesc('ends_at')
            ->first();
    }
}
