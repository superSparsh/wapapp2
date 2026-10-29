<?php

declare(strict_types=1);

namespace App\Domains\Account\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BusinessCustomTermsReviewMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $action,
        public readonly string $businessName,
        public readonly string $termsTitle,
        public readonly ?string $adminNote,
        public readonly bool $suspended = false,
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->action) {
            'approved' => 'Your customer terms were approved',
            'rejected' => 'Your customer terms need changes',
            'removed' => 'Your customer terms were removed',
            default => 'Update on your customer terms',
        };

        return new Envelope(subject: $subject.' — '.config('legal.product_name', 'WapApp'));
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->htmlBody(),
        );
    }

    private function htmlBody(): string
    {
        $product = e((string) config('legal.product_name', 'WapApp'));
        $business = e($this->businessName);
        $title = e($this->termsTitle);
        $note = $this->adminNote !== null && $this->adminNote !== ''
            ? '<p><strong>Admin note:</strong> '.e($this->adminNote).'</p>'
            : '';
        $suspend = $this->suspended
            ? '<p><strong>Account status:</strong> Your workspace was suspended pending compliance review. Contact support to restore access.</p>'
            : '';
        $support = e((string) config('legal.grievance.email', 'support@tittu.in'));

        $intro = match ($this->action) {
            'approved' => "Your custom customer terms (<em>{$title}</em>) for {$business} were approved and may be used with your end customers.",
            'rejected' => "Your custom customer terms (<em>{$title}</em>) for {$business} were rejected. Please edit and resubmit.",
            'removed' => "Your custom customer terms (<em>{$title}</em>) for {$business} were removed by the platform team and are no longer published.",
            default => "There is an update on your custom customer terms (<em>{$title}</em>).",
        };

        return <<<HTML
        <div style="font-family: sans-serif; line-height: 1.5; color: #1a1a1a;">
          <p>Hello,</p>
          <p>{$intro}</p>
          {$note}
          {$suspend}
          <p>Manage terms inside {$product} → Account → Business Terms, or email <a href="mailto:{$support}">{$support}</a>.</p>
        </div>
        HTML;
    }
}
