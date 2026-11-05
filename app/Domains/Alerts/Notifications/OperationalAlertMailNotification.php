<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Notifications;

use App\Domains\Alerts\Support\EmailBrandAssets;
use App\Enums\OperationalAlertType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;

class OperationalAlertMailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $viewData
     * @param  list<array{path: string, as?: string, mime?: string}>  $attachments
     */
    public function __construct(
        public readonly OperationalAlertType $type,
        public readonly string $subject,
        public readonly string $view,
        public readonly array $viewData = [],
        public readonly array $attachments = [],
    ) {
        $this->onQueue((string) config('operational-alerts.queue', 'default'));
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $embedLogo = EmailBrandAssets::logoExists();
        $logoUrl = $embedLogo
            ? EmailBrandAssets::logoCidUrl()
            : EmailBrandAssets::logoPublicUrl();

        $message = (new MailMessage)
            ->subject($this->subject)
            ->view($this->view, array_merge($this->viewData, [
                'logoUrl' => $logoUrl,
            ]));

        foreach ($this->attachments as $attachment) {
            $path = (string) ($attachment['path'] ?? '');
            if ($path === '' || ! is_file($path)) {
                continue;
            }
            $message->attach($path, array_filter([
                'as' => $attachment['as'] ?? basename($path),
                'mime' => $attachment['mime'] ?? null,
            ]));
        }

        if (! $embedLogo) {
            return $message;
        }

        return $message->withSymfonyMessage(function (Email $email): void {
            $part = new DataPart(
                new File(EmailBrandAssets::logoPath()),
                'tittu-logo.jpeg',
                'image/jpeg',
            );
            $part->asInline();
            $part->setContentId(EmailBrandAssets::LOGO_CID);
            $email->addPart($part);
        });
    }
}
