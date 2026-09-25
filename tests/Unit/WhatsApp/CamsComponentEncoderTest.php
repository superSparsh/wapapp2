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
    public function it_shows_alibaba_missing_type_message(): void
    {
        $raw = json_encode([
            'RequestId' => '01A0A9C9-5367-3B21-BC6A-C07BDD4BDF60',
            'Message' => 'Type is mandatory for this action.',
            'Code' => 'MissingType',
        ], JSON_THROW_ON_ERROR);

        $presented = CamsErrorPresenter::present($raw);

        $this->assertSame('Missing template type', $presented['title']);
        $this->assertStringContainsString('Type is mandatory for this action', $presented['message']);
        $this->assertStringNotContainsString('RequestId', $presented['message']);
        $this->assertStringNotContainsString('could not accept', strtolower($presented['message']));
    }

    #[Test]
    public function it_shows_alibaba_file_url_message(): void
    {
        $raw = json_encode([
            'Message' => 'The file can not download.',
            'Code' => 'InvalidParameter.FileUrlError',
        ], JSON_THROW_ON_ERROR);

        $presented = CamsErrorPresenter::present($raw);

        $this->assertSame('Header media error', $presented['title']);
        $this->assertStringContainsString('file can not download', strtolower($presented['message']));
        $this->assertStringContainsString('Re-upload', (string) $presented['hint']);
    }

    #[Test]
    public function it_keeps_alibaba_duplicate_name_message(): void
    {
        $raw = 'code: 400, A template with the same name and language already exists request id: 01A0A9C9-5367-3B21';

        $presented = CamsErrorPresenter::present($raw);

        $this->assertSame('Duplicate template name', $presented['title']);
        $this->assertStringContainsString('same name and language already exists', strtolower($presented['message']));
        $this->assertStringNotContainsString('request id', strtolower($presented['message']));
        $this->assertStringNotContainsString('code: 400', strtolower($presented['message']));
    }

    #[Test]
    public function it_shows_meta_language_deleted_message_as_is(): void
    {
        $raw = 'Message template language is being deleted and can\'t be added. Consider creating a new message template in a different language, or wait 4 weeks before trying to create a template with this language again.';

        $presented = CamsErrorPresenter::present($raw);

        $this->assertSame('Template name on cooldown', $presented['title']);
        $this->assertStringContainsString('being deleted', strtolower($presented['message']));
        $this->assertStringNotContainsString('WhatsApp rejected this template', $presented['message']);
    }

    #[Test]
    public function it_does_not_treat_generic_filler_as_provider_message(): void
    {
        $presented = CamsErrorPresenter::present('WhatsApp rejected this template.');

        $this->assertSame('No error details were returned by WhatsApp.', $presented['message']);
        $this->assertSame('', CamsErrorPresenter::cleanRejectionReason('WhatsApp rejected this template.'));
    }

    #[Test]
    public function clean_rejection_reason_extracts_cams_message_from_json(): void
    {
        $raw = json_encode([
            'RequestId' => '01A0A9C9-5367-3B21-BC6A-C07BDD4BDF60',
            'Message' => 'Message template language is being deleted and can\'t be added.',
            'Code' => 'InvalidParameter',
        ], JSON_THROW_ON_ERROR);

        $cleaned = CamsErrorPresenter::cleanRejectionReason($raw);

        $this->assertStringContainsString('being deleted', strtolower($cleaned));
        $this->assertStringNotContainsString('RequestId', $cleaned);
        $this->assertStringNotContainsString('InvalidParameter', $cleaned);
    }

    #[Test]
    public function it_does_not_invent_vague_accept_filler(): void
    {
        $presented = CamsErrorPresenter::present('');

        $this->assertStringNotContainsString('could not accept this template', strtolower($presented['message']));
        $this->assertStringNotContainsString('please review it', strtolower($presented['message']));
    }

    #[Test]
    public function friendly_error_prefers_provider_text(): void
    {
        $raw = json_encode([
            'Message' => 'The file can not download.',
            'Code' => 'InvalidParameter.FileUrlError',
        ], JSON_THROW_ON_ERROR);

        $friendly = CamsComponentEncoder::friendlyError($raw);

        $this->assertStringContainsString('file can not download', strtolower($friendly));
        $this->assertStringNotContainsString('could not accept this template', strtolower($friendly));
    }
}
