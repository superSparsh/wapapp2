<?php

declare(strict_types=1);

namespace App\Domains\WhatsApp\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Upload template header/carousel sample media to Alibaba CAMS OSS.
 *
 * Flow (documented by CAMS):
 * 1. GetChatappUploadAuthorization(CustSpaceId) → STS + Bucket/Dir/EndPoint
 * 2. OSS PutObject with key = Dir + "/" + filename
 * 3. Use the resulting public HTTPS URL in CreateChatappTemplate HEADER.url
 *
 * Local APP_URL /storage paths are not reachable by Meta/CAMS (FileUrlError).
 */
class CamsTemplateMediaUploader
{
    public function __construct(
        private readonly AlibabaCamsClient $camsClient,
    ) {}

    public function uploadLocalPath(string $relativePath, string $custSpaceId, ?string $preferredName = null): string
    {
        $disk = Storage::disk((string) config('templates.header_media_disk', 'public'));
        $path = ltrim(str_replace('\\', '/', $relativePath), '/');

        if ($path === '' || str_contains($path, '..') || ! $disk->exists($path)) {
            throw new RuntimeException('Header media file was not found for WhatsApp upload.');
        }

        $contents = $disk->get($path);
        if (! is_string($contents) || $contents === '') {
            throw new RuntimeException('Header media file is empty.');
        }

        $mime = (string) ($disk->mimeType($path) ?: 'application/octet-stream');
        $filename = $this->safeFilename($preferredName ?: basename($path));

        return $this->uploadBytes($contents, $filename, $mime, $custSpaceId);
    }

    public function uploadBytes(string $contents, string $filename, string $contentType, string $custSpaceId): string
    {
        $auth = $this->camsClient->getChatappUploadAuthorizationData($custSpaceId);
        $dir = trim((string) ($auth['Dir'] ?? ''), '/');
        if ($dir === '') {
            throw new RuntimeException('CAMS upload authorization returned an empty Dir.');
        }

        $objectKey = $dir.'/'.time().'_'.bin2hex(random_bytes(4)).'_'.$this->safeFilename($filename);
        $this->putObject($auth, $objectKey, $contents, $contentType !== '' ? $contentType : 'application/octet-stream');

        $url = $this->publicObjectUrl($auth, $objectKey);

        Log::info('Uploaded template media to CAMS OSS', [
            'cust_space_id' => $custSpaceId,
            'object_key' => $objectKey,
            'url' => $url,
        ]);

        return $url;
    }

    /**
     * True when CAMS/Meta can fetch the URL without our app being publicly reachable.
     */
    public function isProviderHostedUrl(?string $url): bool
    {
        $url = trim((string) $url);
        if ($url === '' || ! preg_match('#^https://#i', $url)) {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }

        return str_contains($host, 'aliyuncs.com')
            || str_contains($host, 'aliyun.com')
            || str_contains($host, 'oss-');
    }

    /**
     * @param  array<string, mixed>  $auth
     */
    private function putObject(array $auth, string $objectKey, string $contents, string $contentType): void
    {
        $accessKeyId = (string) ($auth['AccessKeyId'] ?? '');
        $accessKeySecret = (string) ($auth['AccessKeySecret'] ?? '');
        $securityToken = (string) ($auth['SecurityToken'] ?? '');
        $bucket = (string) ($auth['BucketName'] ?? '');
        $endpointHost = $this->endpointHost((string) ($auth['EndPoint'] ?? ''));

        if ($accessKeyId === '' || $accessKeySecret === '' || $securityToken === '' || $bucket === '' || $endpointHost === '') {
            throw new RuntimeException('CAMS upload authorization is incomplete.');
        }

        $date = gmdate('D, d M Y H:i:s \G\M\T');
        $canonicalizedOssHeaders = 'x-oss-security-token:'.$securityToken."\n";
        $canonicalizedResource = '/'.$bucket.'/'.$objectKey;
        $stringToSign = "PUT\n\n{$contentType}\n{$date}\n{$canonicalizedOssHeaders}{$canonicalizedResource}";
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $accessKeySecret, true));

        $url = 'https://'.$bucket.'.'.$endpointHost.'/'.$objectKey;

        $response = Http::withHeaders([
            'Date' => $date,
            'Content-Type' => $contentType,
            'Authorization' => 'OSS '.$accessKeyId.':'.$signature,
            'x-oss-security-token' => $securityToken,
        ])
            ->withBody($contents, $contentType)
            ->timeout(60)
            ->put($url);

        if (! $response->successful()) {
            Log::error('CAMS OSS PutObject failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'object_key' => $objectKey,
            ]);

            throw new RuntimeException('Failed to upload media to Alibaba OSS (HTTP '.$response->status().').');
        }
    }

    /**
     * @param  array<string, mixed>  $auth
     */
    private function publicObjectUrl(array $auth, string $objectKey): string
    {
        $bucket = (string) ($auth['BucketName'] ?? '');
        $endpointHost = $this->endpointHost((string) ($auth['EndPoint'] ?? ''));

        return 'https://'.$bucket.'.'.$endpointHost.'/'.$objectKey;
    }

    private function endpointHost(string $endPoint): string
    {
        $endPoint = trim($endPoint);
        if ($endPoint === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $endPoint)) {
            return (string) parse_url($endPoint, PHP_URL_HOST);
        }

        return ltrim($endPoint, '/');
    }

    private function safeFilename(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $name) ?? 'media';
        $name = trim($name, '._-');

        return $name !== '' ? $name : 'media.bin';
    }
}
