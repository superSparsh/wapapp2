<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Services;

use App\Domains\Billing\Services\WalletService;
use App\Enums\OperationalAlertType;
use App\Enums\SubscriptionStatus;
use App\Models\NotificationContact;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WhatsappLine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AlertDispatcher
{
    public function __construct(
        private readonly OperationalAlertService $alerts,
        private readonly PlatformAlertService $platform,
        private readonly WalletService $wallet,
    ) {}

    /**
     * @return bool true when at least one plan-expiration email/WhatsApp was dispatched
     */
    public function planExpiration(?Subscription $subscription, int $daysLeft, object $plan, ?User $user = null, ?Carbon $endsAt = null): bool
    {
        $endsAt ??= $subscription?->ends_at;

        $reminderDays = array_values(array_unique(array_map(
            'intval',
            (array) config('operational-alerts.plan_expiration.reminder_days', [30, 7, 3, 1]),
        )));
        rsort($reminderDays);

        $meta = is_array($subscription?->metadata) ? $subscription->metadata : [];
        $sent = array_map('strval', (array) ($meta['plan_expiration_alerts_sent'] ?? []));

        // Merge tenant.settings dedupe when subscription row is missing / never tracked.
        $tenantId = tenant('id');
        $tenant = $tenantId
            ? tenancy()->central(fn () => Tenant::query()->find($tenantId))
            : null;
        $tenantSettings = is_array($tenant?->settings) ? $tenant->settings : [];
        $sent = array_values(array_unique(array_merge(
            $sent,
            array_map('strval', (array) ($tenantSettings['plan_expiration_alerts_sent'] ?? [])),
        )));

        // Catch-up: once a reminder threshold is crossed (daysLeft <= threshold) and not yet
        // marked, send one mail with the real daysLeft (exact-day cron misses no longer drop).
        $pendingThresholds = array_values(array_filter(
            $reminderDays,
            static fn (int $threshold): bool => $daysLeft <= $threshold && ! in_array((string) $threshold, $sent, true),
        ));

        if ($pendingThresholds === []) {
            return false;
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
            'ends_at' => $endsAt?->format('d M Y') ?? '',
        ];

        $template = $daysLeft <= 3
            ? (string) config('operational-alerts.plan_expiration.whatsapp_template')
            : (string) config('operational-alerts.plan_expiration.whatsapp_renewal_template');

        $whatsappParams = [
            'customer_name' => (string) ($user?->name ?? 'Customer'),
            'plan_name' => (string) ($plan->name ?? 'Plan'),
            'days_left' => $daysLeft,
            'ends_at' => $endsAt?->format('d M Y') ?? '',
        ];

        $emailSubject = $daysLeft === 0
            ? 'Your plan expires today'
            : "Your plan expires in {$daysLeft} day(s)";

        // Billing-critical: send mail synchronously so a stuck queue cannot drop these.
        $this->alerts->notifyTenantContacts(
            type: OperationalAlertType::PlanExpiration,
            emailSubject: $emailSubject,
            emailView: 'emails.plan-expiration',
            emailData: $emailData,
            whatsappTemplate: $template,
            whatsappParams: $whatsappParams,
            owner: $user,
            sync: true,
        );

        // Billing-critical fallback (legacy always emailed/WhatsApp'd the owner).
        if (! $this->alerts->alertsEnabled() || ! $this->tenantHasPlanExpirationContacts($user)) {
            if (filled($user?->email)) {
                $this->alerts->notifyEmails(
                    [(string) $user->email],
                    OperationalAlertType::PlanExpiration,
                    $emailSubject,
                    'emails.plan-expiration',
                    $emailData,
                    sync: true,
                );
            }
            if (filled($user?->phone) && $template !== '') {
                $this->alerts->notifyWhatsAppNumbers([(string) $user->phone], $template, $whatsappParams);
            }
        }

        $adminSubject = $daysLeft === 0
            ? 'Plan expires today - '.($company ?? 'tenant')
            : "Plan expiring in {$daysLeft} day(s) - ".($company ?? 'tenant');

        $this->platform->notifyAdmins(
            OperationalAlertType::PlanExpiration,
            $adminSubject,
            'emails.plan-expiration-admin',
            $emailData,
            sync: true,
        );

        // Admin WhatsApp (WHATSAPP_TO_NUMBERS_PLAN_EXPIRATION) — was configured but never sent.
        $adminWa = (array) config('operational-alerts.admin_whatsapp_numbers', []);
        if ($adminWa !== [] && $template !== '') {
            $this->alerts->notifyWhatsAppNumbers($adminWa, $template, $whatsappParams);
        }

        foreach ($pendingThresholds as $threshold) {
            $sent[] = (string) $threshold;
        }
        $sent = array_values(array_unique($sent));

        if ($subscription !== null) {
            $subscription->update([
                'metadata' => array_merge($meta, ['plan_expiration_alerts_sent' => $sent]),
            ]);
        }

        if ($tenant !== null) {
            tenancy()->central(function () use ($tenant, $sent): void {
                $fresh = Tenant::query()->find($tenant->id);
                if ($fresh === null) {
                    return;
                }
                $settings = is_array($fresh->settings) ? $fresh->settings : [];
                $settings['plan_expiration_alerts_sent'] = $sent;
                $fresh->settings = $settings;
                $fresh->save();
            });
        }

        return true;
    }

    private function tenantHasPlanExpirationContacts(?User $owner): bool
    {
        return NotificationContact::query()
            ->when($owner !== null, fn ($q) => $q->where('user_id', $owner->id))
            ->exists();
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
                'recipient_name' => 'there',
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
            emailSubject: 'Low wallet balance - please recharge',
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
            emailSubject: 'WhatsApp Business Account Status Update',
            emailView: 'emails.alerts.phone-quality-changed',
            emailData: [
                'phone' => $line->phone,
                'display_name' => $line->display_name,
                'user_name' => '',
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

        $adminSubject = match ($status) {
            'eligible' => 'Customer readiness: eligible',
            'not_eligible' => 'Customer readiness: not eligible',
            default => 'Customer readiness: submitted',
        };

        $this->platform->notifyAdmins($type, $adminSubject, $view, $submission, $adminEmails);

        $customerEmail = (string) ($submission['customer_email'] ?? $submission['email'] ?? '');
        if ($customerEmail !== '') {
            $customerSubject = match ($status) {
                'eligible' => 'You’re eligible for WhatsApp onboarding',
                'not_eligible' => 'Action needed on your readiness form',
                default => 'We received your readiness submission',
            };

            $this->alerts->notifyEmails(
                [$customerEmail],
                $type,
                $customerSubject,
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
     * @param  array<string, mixed>  $digest  Legacy-shaped summary from WhatsAppHealthDigestService::buildSummary()
     * @param  list<string>|null  $overrideEmails
     */
    public function whatsappHealthDigest(array $digest, ?array $overrideEmails = null): void
    {
        $emails = $overrideEmails;
        if ($emails === null) {
            $emails = array_values(array_filter(array_map(
                'trim',
                explode(',', (string) config('services.wa_health.digest_emails', ''))
            )));
        } else {
            $emails = array_values(array_filter(array_map('trim', $emails)));
        }

        $generatedAt = $digest['generatedAt'] ?? now();
        $dateLabel = $generatedAt instanceof \DateTimeInterface
            ? $generatedAt->format('j M Y')
            : now()->format('j M Y');

        $subject = is_string($digest['email']['subject'] ?? null) && $digest['email']['subject'] !== ''
            ? (string) $digest['email']['subject']
            : __('wa_health.digest_subject', ['date' => $dateLabel]);

        $this->platform->notifyAdmins(
            OperationalAlertType::WhatsappHealthDigest,
            $subject,
            'emails.alerts.wa-health-digest',
            [
                'summary' => $digest,
                'admin' => null,
                'healthUrl' => route('admin.whatsapp-health.index'),
                'messagePerformanceUrl' => route('admin.message-performance.index'),
            ],
            $emails !== [] ? $emails : null,
            // Test / override recipients send immediately so queued workers with stale code cannot deliver the old template.
            sync: $overrideEmails !== null,
        );
    }

    /**
     * @param  array{
     *     generated_at?: \DateTimeInterface|string,
     *     expired?: list<array<string, mixed>>,
     *     expiring_within_30?: list<array<string, mixed>>,
     *     expiring_30_90?: list<array<string, mixed>>,
     * }  $report
     * @param  list<string>|null  $overrideEmails
     */
    public function accountExpirationReport(array $report, ?array $overrideEmails = null): void
    {
        $emails = $overrideEmails;
        if ($emails === null) {
            $emails = array_values(array_filter(array_map(
                'trim',
                explode(',', (string) (config('services.account_expiration.report_emails') ?: ''))
            )));
        }

        // Sync send so the email uses the current blade (avoids stale queued workers / view cache).
        $this->platform->notifyAdmins(
            OperationalAlertType::AccountExpirationReport,
            'Account expiration report - '.now()->format('F Y'),
            'emails.alerts.account-expiration-report',
            [
                'report' => $report,
                'customersUrl' => route('admin.customers.index'),
                'logoUrl' => \App\Domains\Alerts\Support\EmailBrandAssets::logoPublicUrl(),
            ],
            $emails !== [] ? $emails : null,
            sync: true,
        );
    }

    /**
     * @param  array<string, string|int|float>  $params
     */
    public function calendarWhatsApp(string $templateKey, string $toPhone, array $params): bool
    {
        $template = (string) config($templateKey, '');
        if ($template === '' || $toPhone === '') {
            return false;
        }

        return $this->alerts->notifyWhatsAppNumbers([$toPhone], $template, $params);
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
