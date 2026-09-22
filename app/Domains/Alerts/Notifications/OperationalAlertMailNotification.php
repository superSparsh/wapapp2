<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Notifications;

use App\Enums\OperationalAlertType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OperationalAlertMailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $viewData
     */
    public function __construct(
        public readonly OperationalAlertType $type,
        public readonly string $subject,
        public readonly string $view,
        public readonly array $viewData = [],
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
        return (new MailMessage)
            ->subject($this->subject)
            ->view($this->view, $this->viewData);
    }
}
