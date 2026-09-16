<?php

declare(strict_types=1);

namespace Tests\Unit\WhatsApp;

use App\Domains\WhatsApp\Services\AlibabaCamsClient;
use App\Domains\WhatsApp\Services\CamsTemplateMediaUploader;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CamsTemplateMediaUploaderTest extends TestCase
{
    #[Test]
    public function it_detects_alibaba_hosted_urls(): void
    {
        $uploader = new CamsTemplateMediaUploader($this->createMock(AlibabaCamsClient::class));

        $this->assertTrue($uploader->isProviderHostedUrl(
            'https://whatsapp-prod-oss-bucket.oss-ap-southeast-1.aliyuncs.com/123/file.jpg'
        ));
        $this->assertFalse($uploader->isProviderHostedUrl('https://localhost/storage/templates/headers/a.jpg'));
        $this->assertFalse($uploader->isProviderHostedUrl('/storage/templates/headers/a.jpg'));
        $this->assertFalse($uploader->isProviderHostedUrl(null));
    }
}
