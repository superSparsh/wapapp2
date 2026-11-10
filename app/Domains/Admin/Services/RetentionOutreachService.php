<?php

declare(strict_types=1);

namespace App\Domains\Admin\Services;

use App\Domains\Alerts\Services\OperationalAlertService;
use App\Domains\Alerts\Services\PlatformAlertService;
use App\Enums\OperationalAlertType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Manual retention outreach: WhatsApp + email to the customer and configured admin recipients.
 */
class RetentionOutreachService
{
    public function __construct(
        private readonly OperationalAlertService $alerts,
        private readonly PlatformAlertService $platform,
        private readonly RetentionService $retention,
    ) {}

    /**
     * @return array{ok: bool, message: string}
     */
    public function sendWhatsApp(Tenant $tenant, string $adminName): array
    {
        $context = $this->buildContext($tenant);
        $template = $this->templateForDaysLeft($context['days_left']);

        if ($template === '') {
            return ['ok' => false, 'message' => 'WhatsApp renewal template is not configured.'];
        }

        $customerPhone = trim((string) ($tenant->phone ?? ''));
        $adminPhones = (array) config('operational-alerts.admin_whatsapp_numbers', []);
        $targets = array_values(array_unique(array_filter(array_merge(
            $customerPhone !== '' ? [$customerPhone] : [],
            $adminPhones,
        ))));

        if ($targets === []) {
            return ['ok' => false, 'message' => 'No customer phone or admin WhatsApp numbers configured.'];
        }

        $result = $this->alerts->notifyWhatsAppNumbersResult($targets, $template, $context['whatsapp_params']);
        if (! $result['ok']) {
            Log::warning('Retention WhatsApp outreach failed', [
                'tenant_id' => $tenant->id,
                'error' => $result['error'],
            ]);

            return [
                'ok' => false,
                'message' => $result['error'] ?: 'WhatsApp send failed. Check template / sender config.',
            ];
        }

        $this->retention->addNote(
            $tenant,
            sprintf(
                'WhatsApp renewal reminder sent to %s.',
                $this->formatRecipients(
                    customer: $customerPhone !== '' ? $customerPhone : null,
                    adminCount: count($adminPhones),
                    channel: 'WhatsApp',
                ),
            ),
            'whatsapp_sent',
            $adminName,
        );

        return ['ok' => true, 'message' => 'WhatsApp renewal reminder sent to customer and admin numbers.'];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function sendEmail(Tenant $tenant, string $adminName): array
    {
        $context = $this->buildContext($tenant);
        $customerEmail = trim((string) ($tenant->email ?? ''));
        $adminEmails = (array) config('operational-alerts.admin_emails', []);

        if ($customerEmail === '' && $adminEmails === []) {
            return ['ok' => false, 'message' => 'No customer email or admin emails configured.'];
        }

        $daysLeft = $context['days_left'];
        $customerSubject = $daysLeft === null
            ? 'Plan renewal reminder'
            : ($daysLeft <= 0
                ? 'Your plan has expired — renew to continue'
                : "Your plan expires in {$daysLeft} day(s)");

        if ($customerEmail !== '') {
            $this->alerts->notifyEmails(
                [$customerEmail],
                OperationalAlertType::PlanExpiration,
                $customerSubject,
                'emails.plan-expiration',
                $context['email_data'],
                sync: true,
            );
        }

        if ($adminEmails !== []) {
            $company = (string) ($tenant->company_name ?: $tenant->name);
            $adminSubject = $daysLeft === null
                ? 'Retention outreach - '.$company
                : ($daysLeft <= 0
                    ? 'Plan expired - '.$company
                    : "Plan expiring in {$daysLeft} day(s) - ".$company);

            $this->platform->notifyAdmins(
                OperationalAlertType::PlanExpiration,
                $adminSubject,
                'emails.plan-expiration-admin',
                $context['email_data'],
                sync: true,
            );
        }

        $this->retention->addNote(
            $tenant,
            sprintf(
                'Email renewal reminder sent to %s.',
                $this->formatRecipients(
                    customer: $customerEmail !== '' ? $customerEmail : null,
                    adminCount: count($adminEmails),
                    channel: 'email',
                ),
            ),
            'email_sent',
            $adminName,
        );

        return ['ok' => true, 'message' => 'Email renewal reminder sent to customer and admin inboxes.'];
    }

    /**
     * @return array{
     *   days_left: ?int,
     *   email_data: array<string, mixed>,
     *   whatsapp_params: array<string, string|int|float>
     * }
     */
    private function buildContext(Tenant $tenant): array
    {
        $tenant->loadMissing('plan');
        $detail = $this->retention->detail($tenant);
        $daysLeft = $detail['days_left'];
        $validUntil = $detail['valid_until'] instanceof Carbon ? $detail['valid_until'] : null;
        $support = (array) config('operational-alerts.support', []);
        $owner = $this->resolveOwner($tenant);
        $plan = $tenant->plan ?? (object) ['name' => 'Plan'];

        $userDetails = (object) [
            'first_name' => $owner?->name ? explode(' ', (string) $owner->name)[0] : 'Customer',
            'last_name' => '',
            'email' => $owner?->email ?: $tenant->email,
        ];

        return [
            'days_left' => $daysLeft,
            'email_data' => [
                'user_details' => $userDetails,
                'subscription' => null,
                'plan_details' => $plan,
                'company_name' => $tenant->company_name ?: $tenant->name,
                'support_phone' => $support['phone'] ?? '',
                'support_whatsapp_url' => $support['whatsapp_url'] ?? '',
                'support_email' => $support['email'] ?? '',
                'days_left' => $daysLeft ?? 0,
                'ends_at' => $validUntil?->format('d M Y') ?? '',
            ],
            'whatsapp_params' => [
                // Never send empty strings — CAMS returns InvalidParameter ("must not be null").
                'customer_name' => $this->nonEmpty(
                    (string) ($owner?->name ?: ($tenant->company_name ?: $tenant->name ?: '')),
                    'Customer',
                ),
                'plan_name' => $this->nonEmpty((string) ($tenant->plan?->name ?? ''), 'Plan'),
                'days_left' => (string) ($daysLeft ?? 0),
                'ends_at' => $this->nonEmpty($validUntil?->format('d M Y') ?? '', 'N/A'),
            ],
        ];
    }

    private function nonEmpty(string $value, string $fallback): string
    {
        $value = trim($value);

        return $value !== '' ? $value : $fallback;
    }

    private function templateForDaysLeft(?int $daysLeft): string
    {
        if ($daysLeft !== null && $daysLeft <= 3) {
            return (string) config('operational-alerts.plan_expiration.whatsapp_template', '');
        }

        return (string) config('operational-alerts.plan_expiration.whatsapp_renewal_template', '');
    }

    private function resolveOwner(Tenant $tenant): ?User
    {
        try {
            return $tenant->run(function (): ?User {
                return User::query()->orderBy('id')->first();
            });
        } catch (\Throwable $e) {
            Log::notice('Retention outreach could not resolve tenant owner', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function formatRecipients(?string $customer, int $adminCount, string $channel): string
    {
        $parts = [];
        if ($customer !== null && $customer !== '') {
            $parts[] = 'customer ('.$customer.')';
        }
        if ($adminCount > 0) {
            $parts[] = $adminCount === 1
                ? '1 admin '.$channel.' recipient'
                : $adminCount.' admin '.$channel.' recipients';
        }

        return $parts === [] ? 'configured recipients' : implode(' + ', $parts);
    }
}
