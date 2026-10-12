<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use App\Domains\Templates\Support\TemplateMessagePreview;
use App\Models\Template;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TemplateMessagePreviewTest extends TestCase
{
    #[Test]
    public function authentication_preview_uses_otp_body_footer_and_copy_button(): void
    {
        $rawBody = '';
        $footer = 'ignored';
        $buttons = [['text' => 'Old', 'type' => 'url', 'url' => 'https://example.com']];
        $headerType = 'text';

        TemplateMessagePreview::applyAuthentication(
            [
                'auth' => [
                    'add_secret_recommendation' => true,
                    'expiration_time' => true,
                    'expiration_minutes' => 15,
                    'auto_fill' => false,
                    'copy_button_text' => 'ignored',
                ],
            ],
            $rawBody,
            $footer,
            $buttons,
            $headerType,
        );

        $this->assertStringContainsString('$(verificationCode)', $rawBody);
        $this->assertStringContainsString('For your security', $rawBody);
        $this->assertSame('This code expires in 15 minutes.', $footer);
        $this->assertSame('none', $headerType);
        $this->assertSame('copy_code', $buttons[0]['type']);
        $this->assertSame('Copy code', $buttons[0]['text']);
    }

    #[Test]
    public function lto_offer_block_uses_discount_and_coupon(): void
    {
        $offer = TemplateMessagePreview::ltoOfferFromPayload(
            [
                'lto' => [
                    'enabled' => true,
                    'discount_introduction' => 'Flash sale',
                    'coupon_code' => 'SAVE10',
                    'expiration_time' => true,
                ],
            ],
            [],
        );

        $this->assertNotNull($offer);
        $this->assertSame('Flash sale', $offer['introduction']);
        $this->assertSame('SAVE10', $offer['coupon_code']);
        $this->assertTrue($offer['show_expiration']);
    }

    #[Test]
    public function authentication_template_detects_category(): void
    {
        $template = new Template(['category' => 'AUTHENTICATION']);

        $this->assertTrue(TemplateMessagePreview::isAuthentication($template, []));
    }
}
