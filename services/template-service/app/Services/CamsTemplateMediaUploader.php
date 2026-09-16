<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Upload template header/carousel sample media to Alibaba CAMS OSS.
 *
 * Flow (CAMS docs):
 * 1. GetChatappUploadAuthorization(CustSpaceId) → STS + Bucket/Dir/EndPoint
 * 2. OSS PutObject with key = Dir + "/" + filename
 * 3. Use the public HTTPS object URL in CreateChatappTemplate HEADER.Url
 *
 * Local APP_URL / auth-gated /storage paths cause InvalidParameter.FileUrlError.
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

        Log::info('CAMS OSS upload starting', [
            'cust_space_id' => $custSpaceId,
            'bucket' => $auth['BucketName'] ?? null,
            'endpoint' => $auth['EndPoint'] ?? null,
            'dir' => $dir,
            'object_key' => $objectKey,
            'bytes' => strlen($contents),
            'content_type' => $contentType,
        ]);

        $this->putObject($auth, $objectKey, $contents, $contentType !== '' ? $contentType : 'application/octet-stream');

        $url = $this->publicObjectUrl($auth, $objectKey);
        $this->assertPubliclyReadable($url);

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

        return str_ends_with($host, '.aliyuncs.com')
            || str_ends_with($host, '.aliyun.com');
    }

    /**
     * @param  array<string, mixed>  $auth
     */
    private function putObject(array $auth, string $objectKey, string $contents, string $contentType): void
    {
        // Prefer public-read so Meta/CAMS can download the sample; fall back if STS forbids ACL.
        try {
            $this->putObjectOnce($auth, $objectKey, $contents, $contentType, publicRead: true);
        } catch (RuntimeException $e) {
            if (! str_contains(strtolower($e->getMessage()), 'acl')
                && ! str_contains(strtolower($e->getMessage()), '403')
                && ! str_contains(strtolower($e->getMessage()), 'accessdenied')) {
                throw $e;
            }

            Log::warning('CAMS OSS PutObject with public-read failed; retrying without ACL', [
                'error' => $e->getMessage(),
                'object_key' => $objectKey,
            ]);

            $this->putObjectOnce($auth, $objectKey, $contents, $contentType, publicRead: false);
        }
    }

    /**
     * @param  array<string, mixed>  $auth
     */
    private function putObjectOnce(array $auth, string $objectKey, string $contents, string $contentType, bool $publicRead): void
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
        $ossHeaders = [
            'x-oss-security-token' => $securityToken,
        ];
        if ($publicRead) {
            $ossHeaders['x-oss-object-acl'] = 'public-read';
        }
        ksort($ossHeaders);

        $canonicalizedOssHeaders = '';
        foreach ($ossHeaders as $name => $value) {
            $canonicalizedOssHeaders .= strtolower($name).':'.$value."\n";
        }

        $canonicalizedResource = '/'.$bucket.'/'.$objectKey;
        $stringToSign = "PUT\n\n{$contentType}\n{$date}\n{$canonicalizedOssHeaders}{$canonicalizedResource}";
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $accessKeySecret, true));

        $url = 'https://'.$bucket.'.'.$endpointHost.'/'.$this->encodeObjectKey($objectKey);

        $headers = [
            'Date: '.$date,
            'Content-Type: '.$contentType,
            'Content-Length: '.strlen($contents),
            'Authorization: OSS '.$accessKeyId.':'.$signature,
        ];
        foreach ($ossHeaders as $name => $value) {
            $headers[] = $name.': '.$value;
        }

        $responseHeaders = '';
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize OSS upload request.');
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $contents,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new RuntimeException('OSS upload network error: '.$error);
        }

        $responseHeaders = is_string($raw) ? substr($raw, 0, $headerSize) : '';
        $responseBody = is_string($raw) ? substr($raw, $headerSize) : '';

        if ($status < 200 || $status >= 300) {
            Log::error('CAMS OSS PutObject failed', [
                'status' => $status,
                'body' => $responseBody,
                'headers' => $responseHeaders,
                'object_key' => $objectKey,
                'public_read' => $publicRead,
            ]);

            throw new RuntimeException('Failed to upload media to Alibaba OSS (HTTP '.$status.'). '.$this->shortBody($responseBody));
        }
    }

    private function assertPubliclyReadable(string $url): void
    {
        try {
            $response = Http::timeout(20)
                ->withHeaders(['User-Agent' => 'wapapp-cams-media-check/1.0'])
                ->get($url);
        } catch (\Throwable $e) {
            throw new RuntimeException('Uploaded media URL is not reachable: '.$e->getMessage());
        }

        if (! $response->successful()) {
            Log::error('CAMS OSS object not publicly readable after upload', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $this->shortBody($response->body()),
            ]);

            throw new RuntimeException(
                'Media uploaded to Alibaba OSS but is not publicly downloadable (HTTP '.$response->status().').'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $auth
     */
    private function publicObjectUrl(array $auth, string $objectKey): string
    {
        $bucket = (string) ($auth['BucketName'] ?? '');
        $endpointHost = $this->endpointHost((string) ($auth['EndPoint'] ?? ''));

        return 'https://'.$bucket.'.'.$endpointHost.'/'.$this->encodeObjectKey($objectKey);
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

    /**
     * Encode each path segment but keep slashes (OSS object key).
     */
    private function encodeObjectKey(string $objectKey): string
    {
        return implode('/', array_map(
            static fn (string $part): string => rawurlencode($part),
            explode('/', $objectKey),
        ));
    }

    private function safeFilename(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $name) ?? 'media';
        $name = trim($name, '._-');

        return $name !== '' ? $name : 'media.bin';
    }

    private function shortBody(string $body): string
    {
        $body = trim(strip_tags($body));

        return \Illuminate\Support\Str::limit($body, 180);
    }
}
