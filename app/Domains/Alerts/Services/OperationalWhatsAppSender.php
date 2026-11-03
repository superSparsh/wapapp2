<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Services;

use App\Support\PhoneNormalizer;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OperationalWhatsAppSender
{
    /**
     * @param  array<string, string|int|float>  $templateParams
     */
    public function sendTemplate(string $toPhone, string $templateCode, array $templateParams = []): bool
    {
        return $this->sendTemplateResult($toPhone, $templateCode, $templateParams)['ok'];
    }

    /**
     * @param  array<string, string|int|float>  $templateParams
     * @return array{ok: bool, error: ?string}
     */
    public function sendTemplateResult(string $toPhone, string $templateCode, array $templateParams = []): array
    {
        if (! (bool) config('operational-alerts.enabled', true)) {
            return ['ok' => false, 'error' => 'Operational WhatsApp alerts are disabled.'];
        }

        $accessKeyId = (string) config('operational-alerts.alibaba.access_key_id', '');
        $accessKeySecret = (string) config('operational-alerts.alibaba.access_key_secret', '');
        $from = (string) config('operational-alerts.whatsapp.from', '');
        $custSpaceId = (string) config('operational-alerts.whatsapp.cust_space_id', '');
        $language = (string) config('operational-alerts.whatsapp.language', 'en_GB');

        if ($accessKeyId === '' || $accessKeySecret === '' || $from === '' || $templateCode === '') {
            Log::warning('Operational WhatsApp alert skipped: missing CAMS config', [
                'template' => $templateCode,
                'to' => $toPhone,
                'has_access_key' => $accessKeyId !== '',
                'has_secret' => $accessKeySecret !== '',
                'has_from' => $from !== '',
            ]);

            return ['ok' => false, 'error' => 'WhatsApp sender config missing (FROM number or Alibaba keys).'];
        }

        $to = $this->formatRecipient($toPhone);
        if ($to === '') {
            return ['ok' => false, 'error' => 'Invalid WhatsApp recipient number.'];
        }

        $payload = [
            'ChannelType' => 'whatsapp',
            'Type' => 'template',
            'TemplateCode' => $templateCode,
            'Language' => $language,
            'From' => $from,
            'To' => $to,
            'TemplateParams' => json_encode($this->stringifyParams($templateParams), JSON_THROW_ON_ERROR),
        ];

        if ($custSpaceId !== '') {
            $payload['CustSpaceId'] = $custSpaceId;
        }

        try {
            $response = $this->signedRequest($payload, $accessKeyId, $accessKeySecret);
        } catch (\Throwable $e) {
            Log::error('Operational WhatsApp alert request failed', [
                'template' => $templateCode,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'error' => 'WhatsApp request failed: '.$e->getMessage()];
        }

        $parsed = $this->parseCamsResponse($response);
        if (! $parsed['ok']) {
            Log::error('Operational WhatsApp alert delivery rejected', [
                'template' => $templateCode,
                'to' => $to,
                'status' => $response->status(),
                'body' => $response->body(),
                'error' => $parsed['error'],
            ]);
        }

        return $parsed;
    }

    /**
     * @param  array<string, string|int|float>  $params
     * @return array<string, string>
     */
    private function stringifyParams(array $params): array
    {
        $out = [];
        foreach ($params as $key => $value) {
            $out[(string) $key] = trim((string) $value);
        }

        return $out;
    }

    /**
     * @return array{ok: bool, error: ?string}
     */
    private function parseCamsResponse(Response $response): array
    {
        $body = $response->json();
        $body = is_array($body) ? $body : [];

        if (! $response->successful()) {
            return ['ok' => false, 'error' => $this->extractCamsError($body, $response->body(), $response->status())];
        }

        $code = Arr::get($body, 'Code')
            ?? Arr::get($body, 'code')
            ?? Arr::get($body, 'Data.Code')
            ?? Arr::get($body, 'data.Code');

        if (is_string($code) && $code !== '' && strtoupper($code) !== 'OK') {
            return ['ok' => false, 'error' => $this->extractCamsError($body, $response->body(), $response->status())];
        }

        return ['ok' => true, 'error' => null];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function extractCamsError(array $body, string $rawBody, int $status): string
    {
        $message = Arr::get($body, 'Message')
            ?? Arr::get($body, 'message')
            ?? Arr::get($body, 'Error.Message')
            ?? Arr::get($body, 'error.message');

        $code = Arr::get($body, 'Code')
            ?? Arr::get($body, 'code')
            ?? Arr::get($body, 'Data.Code');

        if (is_string($message) && trim($message) !== '') {
            $prefix = is_string($code) && $code !== '' ? trim($message).' ('.$code.')' : trim($message);

            return $prefix;
        }

        $trimmed = trim($rawBody);
        if ($trimmed !== '') {
            return 'WhatsApp provider rejected the message (HTTP '.$status.'): '.$trimmed;
        }

        return 'WhatsApp provider rejected the message (HTTP '.$status.').';
    }

    private function formatRecipient(string $phone): string
    {
        $normalized = PhoneNormalizer::normalize($phone) ?? preg_replace('/\D+/', '', $phone) ?? '';
        $normalized = ltrim((string) $normalized, '+');

        return $normalized === '' ? '' : '+'.$normalized;
    }

    /** @param  array<string, string>  $params */
    private function signedRequest(array $params, string $accessKeyId, string $accessKeySecret): Response
    {
        $endpoint = (string) config('operational-alerts.alibaba.endpoint', 'cams.ap-southeast-1.aliyuncs.com');
        $common = [
            'Action' => 'SendChatappMessage',
            'Format' => 'JSON',
            'Version' => '2020-06-06',
            'AccessKeyId' => $accessKeyId,
            'SignatureMethod' => 'HMAC-SHA1',
            'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'SignatureVersion' => '1.0',
            'SignatureNonce' => bin2hex(random_bytes(8)),
        ];

        $query = array_merge($common, $params);
        ksort($query);

        $canonicalized = collect($query)
            ->map(fn (string $value, string $key) => $this->percentEncode($key).'='.$this->percentEncode($value))
            ->implode('&');

        $stringToSign = 'GET&%2F&'.$this->percentEncode($canonicalized);
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $accessKeySecret.'&', true));
        $query['Signature'] = $signature;

        return Http::timeout(15)->get("https://{$endpoint}/", $query);
    }

    private function percentEncode(string $value): string
    {
        return str_replace(['+', '*', '%7E'], ['%20', '%2A', '~'], rawurlencode($value));
    }
}
