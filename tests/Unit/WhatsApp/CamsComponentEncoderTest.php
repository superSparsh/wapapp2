<?php

declare(strict_types=1);

namespace Tests\Unit\WhatsApp;

use App\Domains\WhatsApp\Support\CamsComponentEncoder;
use App\Domains\WhatsApp\Support\CamsErrorPresenter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CamsComponentEncoderTest extends TestCase
{
    #[Test]
    public function it_maps_component_type_to_pascal_case_for_rpc(): void
    {
        $encoded = CamsComponentEncoder::forRpc([
            [
                'type' => 'BODY',
                'text' => 'Hello',
                'format' => 'TEXT',
            ],
            [
                'type' => 'BUTTONS',
                'buttons' => [
                    ['type' => 'URL', 'text' => 'Shop', 'url' => 'https://example.com'],
                ],
            ],
        ]);

        $this->assertSame('BODY', $encoded[0]['Type']);
        $this->assertSame('Hello', $encoded[0]['Text']);
        $this->assertSame('TEXT', $encoded[0]['Format']);
        $this->assertSame('BUTTONS', $encoded[1]['Type']);
        $this->assertSame('URL', $encoded[1]['Buttons'][0]['Type']);
        $this->assertSame('Shop', $encoded[1]['Buttons'][0]['Text']);
        $this->assertArrayNotHasKey('type', $encoded[0]);
    }

    #[Test]
    public function it_json_encodes_components_like_alibaba_sdk_shrink(): void
    {
        $json = CamsComponentEncoder::toJson([
            ['type' => 'BODY', 'text' => 'Hello', 'format' => 'TEXT'],
        ]);

        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsArray($decoded);
        $this->assertSame('BODY', $decoded[0]['Type']);
        $this->assertSame('Hello', $decoded[0]['Text']);
        $this->assertArrayNotHasKey('type', $decoded[0]);
    }

    #[Test]
    public function it_formats_cams_missing_type_errors_for_ui(): void
    {
        $raw = json_encode([
            'RequestId' => '01A0A9C9-5367-3B21-BC6A-C07BDD4BDF60',
            'Message' => 'Type is mandatory for this action.',
            'Code' => 'MissingType',
        ], JSON_THROW_ON_ERROR);

        $presented = CamsErrorPresenter::present($raw);

        $this->assertSame('Template structure incomplete', $presented['title']);
        $this->assertStringContainsString('missing type information', $presented['message']);
        $this->assertStringContainsString('submit again', (string) $presented['hint']);
        $this->assertStringNotContainsString('RequestId', $presented['message']);
    }

    #[Test]
    public function it_formats_file_url_errors_for_ui(): void
    {
        $raw = json_encode([
            'Message' => 'The file can not download.',
            'Code' => 'InvalidParameter.FileUrlError',
        ], JSON_THROW_ON_ERROR);

        $presented = CamsErrorPresenter::present($raw);

        $this->assertSame('Header media unavailable', $presented['title']);
        $this->assertStringContainsString('could not download', $presented['message']);
        $this->assertStringContainsString('Re-upload', (string) $presented['hint']);
    }

    #[Test]
    public function it_polishes_tea_sdk_style_errors_without_request_ids(): void
    {
        $raw = 'code: 400, A template with the same name and language already exists request id: 01A0A9C9-5367-3B21';

        $presented = CamsErrorPresenter::present($raw);

        $this->assertSame('Template name already used', $presented['title']);
        $this->assertStringContainsString('already exists', $presented['message']);
        $this->assertStringNotContainsString('request id', strtolower($presented['message']));
        $this->assertStringNotContainsString('code: 400', strtolower($presented['message']));
    }

    #[Test]
    public function friendly_error_includes_actionable_hint(): void
    {
        $raw = json_encode([
            'Message' => 'The file can not download.',
            'Code' => 'InvalidParameter.FileUrlError',
        ], JSON_THROW_ON_ERROR);

        $friendly = CamsComponentEncoder::friendlyError($raw);

        $this->assertStringContainsString('could not download', $friendly);
        $this->assertStringContainsString('Re-upload', $friendly);
    }
}
