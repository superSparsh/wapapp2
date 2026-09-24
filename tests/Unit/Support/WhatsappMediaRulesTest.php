<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\WhatsappMediaRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WhatsappMediaRulesTest extends TestCase
{
    public function test_limits_match_whatsapp_media_policy(): void
    {
        $this->assertSame(5120, WhatsappMediaRules::maxKb('image'));
        $this->assertSame(14336, WhatsappMediaRules::maxKb('video'));
        $this->assertSame(14336, WhatsappMediaRules::maxKb('document'));
        $this->assertSame(14336, WhatsappMediaRules::maxKb('audio'));
        $this->assertSame(14336, WhatsappMediaRules::absoluteMaxKb());
    }

    public function test_assert_valid_rejects_oversized_image(): void
    {
        $file = UploadedFile::fake()->create('photo.jpg', 6000, 'image/jpeg');

        $this->expectException(ValidationException::class);
        WhatsappMediaRules::assertValid($file, 'image');
    }

    public function test_detect_type_from_extension(): void
    {
        $file = UploadedFile::fake()->create('deck.pptx', 100, 'application/vnd.openxmlformats-officedocument.presentationml.presentation');

        $this->assertSame('document', WhatsappMediaRules::detectType($file));
    }

    public function test_validation_messages_include_uploaded_key(): void
    {
        $messages = WhatsappMediaRules::validationMessages('image');

        $this->assertArrayHasKey('file.uploaded', $messages);
        $this->assertStringContainsString('failed to upload', strtolower($messages['file.uploaded']));
    }

    public function test_client_config_exposes_php_upload_ceiling(): void
    {
        $config = WhatsappMediaRules::clientConfig();

        $this->assertArrayHasKey('php_upload_max_bytes', $config);
        $this->assertGreaterThan(0, $config['php_upload_max_bytes']);
    }
}
