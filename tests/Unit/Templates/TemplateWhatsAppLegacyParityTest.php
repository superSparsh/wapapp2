<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use App\Domains\Templates\Services\TemplateWhatsAppService;
use App\Models\Template;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

final class TemplateWhatsAppLegacyParityTest extends TestCase
{
    #[Test]
    public function example_map_includes_named_header_media_variables(): void
    {
        $template = new Template([
            'category' => 'MARKETING',
            'payload' => array_replace_recursive(Template::defaultPayload(), [
                'header' => [
                    'type' => 'image',
                    'media_url' => '$(product_image)',
                    'use_url' => true,
                ],
                'body' => [
                    'text' => 'Hello $(first_name), check this out',
                    'samples' => ['Ada'],
                ],
            ]),
        ]);
        $template->setRelation('variables', collect());

        $method = new ReflectionMethod(TemplateWhatsAppService::class, 'buildExample');
        $method->setAccessible(true);
        $example = $method->invoke(app(TemplateWhatsAppService::class), $template);

        $this->assertArrayHasKey('first_name', $example);
        $this->assertSame('Ada', $example['first_name']);
        $this->assertArrayHasKey('product_image', $example);
        $this->assertStringStartsWith('https://', $example['product_image']);
        $this->assertArrayNotHasKey('body_text', $example);
    }

    #[Test]
    public function example_map_includes_header_text_variables(): void
    {
        $template = new Template([
            'category' => 'UTILITY',
            'payload' => array_replace_recursive(Template::defaultPayload(), [
                'header' => [
                    'type' => 'text',
                    'text' => 'Hi $(customer_name)',
                ],
                'body' => [
                    'text' => 'Your order is ready.',
                ],
            ]),
        ]);
        $template->setRelation('variables', collect());

        $method = new ReflectionMethod(TemplateWhatsAppService::class, 'buildExample');
        $method->setAccessible(true);
        $example = $method->invoke(app(TemplateWhatsAppService::class), $template);

        $this->assertArrayHasKey('customer_name', $example);
        $this->assertNotSame('', $example['customer_name']);
    }

    #[Test]
    public function lto_templates_map_phone_buttons_to_url_for_cams(): void
    {
        $template = new Template([
            'category' => 'LIMITED_TIME_OFFER',
            'payload' => array_replace_recursive(Template::defaultPayload(), [
                'lto' => [
                    'enabled' => true,
                    'discount_introduction' => '20% off',
                ],
                'body' => ['text' => 'Hello there'],
                'buttons' => [
                    ['text' => 'Copy code', 'type' => 'copy_code', 'url' => 'SAVE20'],
                    ['text' => 'Visit site', 'type' => 'phone', 'url' => 'https://shop.example.com'],
                ],
            ]),
        ]);

        $components = app(TemplateWhatsAppService::class)->buildComponents($template);
        $buttons = collect($components)->firstWhere('type', 'BUTTONS')['buttons'] ?? [];

        $this->assertCount(2, $buttons);
        $this->assertSame('COPY_CODE', $buttons[0]['type']);
        $this->assertSame('URL', $buttons[1]['type']);
        $this->assertSame('https://shop.example.com', $buttons[1]['url']);
    }

    #[Test]
    public function authentication_supported_apps_are_sent_only_with_autofill(): void
    {
        $template = new Template([
            'category' => 'AUTHENTICATION',
            'payload' => array_replace_recursive(Template::defaultPayload(), [
                'auth' => [
                    'enabled' => true,
                    'auto_fill' => false,
                    'copy_button_text' => 'Copy Code',
                    'supported_apps' => [
                        ['package_name' => 'com.example.app', 'signature_hash' => 'abc123'],
                    ],
                ],
            ]),
        ]);

        $components = app(TemplateWhatsAppService::class)->buildComponents($template);
        $button = collect($components)->firstWhere('type', 'BUTTONS')['buttons'][0] ?? [];

        $this->assertSame('COPY_CODE', $button['type']);
        $this->assertArrayNotHasKey('supportedApps', $button);
        $this->assertArrayHasKey('autofillText', $button);
    }

    #[Test]
    public function authentication_includes_supported_apps_when_autofill_enabled(): void
    {
        $template = new Template([
            'category' => 'AUTHENTICATION',
            'payload' => array_replace_recursive(Template::defaultPayload(), [
                'auth' => [
                    'enabled' => true,
                    'auto_fill' => true,
                    'zero_tap' => false,
                    'supported_apps' => [
                        ['package_name' => 'com.example.app', 'signature_hash' => 'abc123'],
                    ],
                ],
            ]),
        ]);

        $components = app(TemplateWhatsAppService::class)->buildComponents($template);
        $button = collect($components)->firstWhere('type', 'BUTTONS')['buttons'][0] ?? [];

        $this->assertSame('ONE_TAP', $button['type']);
        $this->assertCount(1, $button['supportedApps'] ?? []);
        $this->assertSame('com.example.app', $button['supportedApps'][0]['packageName']);
    }
}
