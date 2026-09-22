<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Services;

use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OperationalWhatsAppSender
{
    /**
     * @param  array<string, string|int|float>  $templateParams
     */
    public function sendTemplate(string $toPhone, string $templateCode, array $templateParams = []): bool
    {
        if (! (bool) config('operational-alerts.enabled', true)) {
            return false;
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
            ]);

            return false;
        }

        $to = $this->formatRecipient($toPhone);
        if ($to === '') {
            return false;
        }

        $payload = [
            'ChannelType' => 'whatsapp',
            'Type' => 'template',
            'TemplateCode' => $templateCode,
            'Language' => $language,
            'From' => $from,
            'To' => $to,
            'TemplateParams' => json_encode($templateParams, JSON_THROW_ON_ERROR),
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

            return false;
        }

        if (! $response->successful()) {
            Log::error('Operational WhatsApp alert delivery rejected', [
                'template' => $templateCode,
                'to' => $to,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        return true;
    }

    private function formatRecipient(string $phone): string
    {
        $normalized = PhoneNormalizer::normalize($phone) ?? preg_replace('/\D+/', '', $phone) ?? '';
        $normalized = ltrim((string) $normalized, '+');

        return $normalized === '' ? '' : '+'.$normalized;
    }

    /** @param  array<string, string>  $params */
    private function signedRequest(array $params, string $accessKeyId, string $accessKeySecret): \Illuminate\Http\Client\Response
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
