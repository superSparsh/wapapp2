<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services\OtpDelivery;

use App\Domains\Auth\Contracts\LoginOtpDeliverer;
use App\Domains\Auth\Exceptions\OtpDeliveryException;
use App\Models\TenantUserAccess;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppLoginOtpDeliverer implements LoginOtpDeliverer
{
    public function send(string $phone, string $otp, TenantUserAccess $access): void
    {
        $accessKeyId = config('login-otp.alibaba.access_key_id');
        $accessKeySecret = config('login-otp.alibaba.access_key_secret');
        $from = config('login-otp.whatsapp.from');
        $templateCode = config('login-otp.whatsapp.template_code');
        $custSpaceId = config('login-otp.whatsapp.cust_space_id');
        $templateParam = config('login-otp.whatsapp.template_param', 'verificationCode');

        if (! $accessKeyId || ! $accessKeySecret || ! $from || ! $templateCode) {
            Log::warning('WhatsApp login OTP is not configured. Falling back to log delivery.', [
                'phone' => $phone,
                'tenant_id' => $access->tenant_id,
            ]);

            app(LogLoginOtpDeliverer::class)->send($phone, $otp, $access);

            return;
        }

        $to = $this->formatRecipient($phone);

        $payload = [
            'ChannelType' => 'whatsapp',
            'Type' => 'template',
            'TemplateCode' => $templateCode,
            'Language' => 'en_GB',
            'From' => $from,
            'To' => $to,
            'TemplateParams' => json_encode([$templateParam => $otp], JSON_THROW_ON_ERROR),
        ];

        if ($custSpaceId) {
            $payload['CustSpaceId'] = $custSpaceId;
        }

        $response = $this->signedRequest($payload, $accessKeyId, $accessKeySecret);

        if (! $response->successful()) {
            Log::error('WhatsApp login OTP delivery failed', [
                'phone' => $phone,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw OtpDeliveryException::make();
        }
    }

    private function formatRecipient(string $phone): string
    {
        $normalized = PhoneNormalizer::normalize($phone) ?? $phone;

        return str_starts_with($normalized, '+') ? $normalized : '+'.$normalized;
    }

    /** @param  array<string, string>  $params */
    private function signedRequest(array $params, string $accessKeyId, string $accessKeySecret): \Illuminate\Http\Client\Response
    {
        $endpoint = config('login-otp.alibaba.endpoint', 'cams.ap-southeast-1.aliyuncs.com');
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
