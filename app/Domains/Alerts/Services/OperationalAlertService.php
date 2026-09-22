<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Services;

use App\Domains\Alerts\Notifications\OperationalAlertMailNotification;
use App\Enums\NotificationContactType;
use App\Enums\OperationalAlertType;
use App\Models\AccountPreference;
use App\Models\NotificationContact;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Sends operational alerts to tenant Business Alerts contacts when alerts_enabled.
 */
class OperationalAlertService
{
    public function __construct(
        private readonly OperationalWhatsAppSender $whatsAppSender,
    ) {}

    public function alertsEnabled(): bool
    {
        if (! (bool) config('operational-alerts.enabled', true)) {
            return false;
        }

        return (bool) AccountPreference::current()->alerts_enabled;
    }

    /**
     * @param  array<string, mixed>  $emailData
     * @param  array<string, string|int|float>  $whatsappParams
     */
    public function notifyTenantContacts(
        OperationalAlertType $type,
        string $emailSubject,
        string $emailView,
        array $emailData = [],
        ?string $whatsappTemplate = null,
        array $whatsappParams = [],
        ?User $owner = null,
    ): void {
        if (! $this->alertsEnabled()) {
            return;
        }

        $contacts = NotificationContact::query()
            ->when($owner !== null, fn ($q) => $q->where('user_id', $owner->id))
            ->orderBy('id')
            ->get();

        if ($contacts->isEmpty()) {
            Log::info('Operational alert skipped: no notification contacts', [
                'type' => $type->value,
            ]);

            return;
        }

        foreach ($contacts as $contact) {
            if ($contact->type === NotificationContactType::Email) {
                $this->sendEmail($contact->contact_info, $type, $emailSubject, $emailView, $emailData);
            }

            if ($contact->type === NotificationContactType::Whatsapp && filled($whatsappTemplate)) {
                $this->whatsAppSender->sendTemplate(
                    $contact->contact_info,
                    $whatsappTemplate,
                    $whatsappParams,
                );
            }
        }
    }

    /**
     * Force-send to specific emails (ignores alerts_enabled) — e.g. customer wallet receipts.
     *
     * @param  list<string>  $emails
     * @param  array<string, mixed>  $emailData
     */
    public function notifyEmails(
        array $emails,
        OperationalAlertType $type,
        string $subject,
        string $view,
        array $emailData = [],
    ): void {
        foreach (array_unique(array_filter($emails)) as $email) {
            $this->sendEmail((string) $email, $type, $subject, $view, $emailData);
        }
    }

    /**
     * @param  list<string>  $phones
     * @param  array<string, string|int|float>  $params
     */
    public function notifyWhatsAppNumbers(array $phones, string $templateCode, array $params = []): void
    {
        foreach (array_unique(array_filter($phones)) as $phone) {
            $this->whatsAppSender->sendTemplate((string) $phone, $templateCode, $params);
        }
    }

    /**
     * @param  array<string, mixed>  $emailData
     */
    private function sendEmail(
        string $email,
        OperationalAlertType $type,
        string $subject,
        string $view,
        array $emailData,
    ): void {
        $email = trim($email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            Notification::route('mail', $email)->notify(
                new OperationalAlertMailNotification($type, $subject, $view, $emailData)
            );
        } catch (\Throwable $e) {
            Log::error('Operational alert email failed', [
                'type' => $type->value,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
