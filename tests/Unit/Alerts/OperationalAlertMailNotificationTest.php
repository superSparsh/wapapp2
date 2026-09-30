<?php

declare(strict_types=1);

namespace Tests\Unit\Alerts;

use App\Domains\Alerts\Notifications\OperationalAlertMailNotification;
use App\Domains\Alerts\Support\EmailBrandAssets;
use App\Enums\OperationalAlertType;
use Illuminate\Notifications\AnonymousNotifiable;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Tests\TestCase;

class OperationalAlertMailNotificationTest extends TestCase
{
    public function test_to_mail_embeds_logo_as_cid(): void
    {
        $this->assertTrue(EmailBrandAssets::logoExists());

        $notification = new OperationalAlertMailNotification(
            OperationalAlertType::LowWallet,
            'Low wallet',
            'emails.alerts.low-wallet',
            ['balance' => 10, 'threshold' => 100, 'currency' => 'INR', 'context' => 'test'],
        );

        $mail = $notification->toMail(new AnonymousNotifiable);
        $symfony = new Email;
        foreach ($mail->callbacks as $callback) {
            $callback($symfony);
        }

        $this->assertSame(EmailBrandAssets::logoCidUrl(), $mail->viewData['logoUrl'] ?? null);

        $embedded = false;
        foreach ($symfony->getAttachments() as $part) {
            if (! $part instanceof DataPart) {
                continue;
            }
            $cid = (string) $part->getContentId();
            if ($cid === EmailBrandAssets::LOGO_CID) {
                $embedded = true;
                break;
            }
        }

        $this->assertTrue($embedded, 'Expected tittu logo to be embedded with CID wapapp-logo');
    }
}
