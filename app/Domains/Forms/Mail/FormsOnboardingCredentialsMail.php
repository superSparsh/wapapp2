<?php

declare(strict_types=1);

namespace App\Domains\Forms\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FormsOnboardingCredentialsMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  list<string>  $attachmentPaths
     */
    public function __construct(
        public readonly string $loginEmail,
        public readonly string $tempPassword,
        public readonly string $loginUrl,
        public readonly string $customerName,
        public readonly string $businessName,
        public readonly array $attachmentPaths = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Tittu / WhatsApp Automation login credentials',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->buildHtml(),
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];
        foreach ($this->attachmentPaths as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                $attachments[] = Attachment::fromPath($path);
            }
        }

        return $attachments;
    }

    private function buildHtml(): string
    {
        $name = e($this->customerName !== '' ? $this->customerName : 'there');
        $email = e($this->loginEmail);
        $password = e($this->tempPassword);
        $url = e($this->loginUrl);
        $business = e($this->businessName);

        return <<<HTML
<!DOCTYPE html>
<html><body style="font-family:Arial,Helvetica,sans-serif;color:#111827;background:#f4f5f7;padding:24px;">
  <table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
  <table width="600" style="background:#fff;border-radius:12px;overflow:hidden;">
    <tr><td style="background:#39ac31;color:#fff;padding:24px;">
      <h1 style="margin:0;font-size:22px;">Welcome to Tittu WhatsApp Automation</h1>
      <p style="margin:8px 0 0;opacity:.9;">Hi {$name}</p>
    </td></tr>
    <tr><td style="padding:28px;">
      <p>Your account for <strong>{$business}</strong> is ready. Please sign in and change your temporary password.</p>
      <p><strong>Login email:</strong> {$email}<br>
      <strong>Temporary password:</strong> {$password}</p>
      <p><a href="{$url}" style="display:inline-block;background:#39ac31;color:#fff;padding:12px 18px;border-radius:8px;text-decoration:none;font-weight:bold;">Open login</a></p>
      <p style="font-size:13px;color:#64748b;">If the button does not work, open: {$url}</p>
    </td></tr>
  </table>
  </td></tr></table>
</body></html>
HTML;
    }
}
