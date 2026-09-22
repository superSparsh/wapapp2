<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Services;

use App\Enums\OperationalAlertType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Admin digests + developer WhatsApp ops alerts (not tenant Business Alerts contacts).
 */
class PlatformAlertService
{
    public function __construct(
        private readonly OperationalAlertService $alerts,
        private readonly OperationalWhatsAppSender $whatsApp,
    ) {}

    /**
     * @param  array<string, mixed>  $emailData
     */
    public function notifyAdmins(
        OperationalAlertType $type,
        string $subject,
        string $view,
        array $emailData = [],
        ?array $overrideEmails = null,
    ): void {
        $emails = $overrideEmails ?? (array) config('operational-alerts.admin_emails', []);
        if ($emails === []) {
            Log::info('Platform admin alert skipped: no admin emails configured', [
                'type' => $type->value,
            ]);

            return;
        }

        $this->alerts->notifyEmails($emails, $type, $subject, $view, $emailData);
    }

    /**
     * @param  array<string, string|int|float>  $params
     */
    public function notifyDevelopersWhatsApp(string $message, array $params = []): void
    {
        $numbers = (array) config('operational-alerts.developer_whatsapp_numbers', []);
        $template = (string) config('operational-alerts.error.whatsapp_template', '');

        if ($numbers === [] || $template === '') {
            Log::info('Developer WhatsApp alert skipped: missing numbers or template', [
                'message' => $message,
            ]);

            return;
        }

        $payload = array_merge([
            'error_message' => mb_substr($message, 0, 500),
            'message' => mb_substr($message, 0, 500),
        ], $params);

        foreach ($numbers as $number) {
            $this->whatsApp->sendTemplate((string) $number, $template, $payload);
        }
    }

    public function notifyDeveloperError(string $context, string $detail, ?string $throttleKey = null, int $throttleSeconds = 900): void
    {
        $key = 'ops-dev-error:'.($throttleKey ?? md5($context.'|'.$detail));
        if (! Cache::add($key, 1, $throttleSeconds)) {
            return;
        }

        $this->notifyDevelopersWhatsApp("[{$context}] {$detail}");
    }
}
