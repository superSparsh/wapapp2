<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Services;

use App\Domains\Templates\Support\CamsTemplateIdentity;
use App\Support\PhoneNormalizer;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OperationalWhatsAppSender
{
    /**
     * @param  array<string, string|int|float>  $templateParams
     * @param  array{from?: string, cust_space_id?: string, language?: string}|null  $sender
     */
    public function sendTemplate(string $toPhone, string $templateCode, array $templateParams = [], ?array $sender = null): bool
    {
        return $this->sendTemplateResult($toPhone, $templateCode, $templateParams, $sender)['ok'];
    }

    /**
     * @param  array<string, string|int|float>  $templateParams
     * @param  array{from?: string, cust_space_id?: string, language?: string}|null  $sender
     * @return array{ok: bool, error: ?string}
     */
    public function sendTemplateResult(string $toPhone, string $templateCode, array $templateParams = [], ?array $sender = null): array
    {
        if (! (bool) config('operational-alerts.enabled', true)) {
            return ['ok' => false, 'error' => 'Operational WhatsApp alerts are disabled.'];
        }

        $accessKeyId = (string) config('operational-alerts.alibaba.access_key_id', '');
        $accessKeySecret = (string) config('operational-alerts.alibaba.access_key_secret', '');
        $from = trim((string) ($sender['from'] ?? config('operational-alerts.whatsapp.from', '')));
        $custSpaceId = trim((string) ($sender['cust_space_id'] ?? config('operational-alerts.whatsapp.cust_space_id', '')));
        $language = CamsTemplateIdentity::language(
            (string) ($sender['language'] ?? config('operational-alerts.whatsapp.language', 'en_GB')),
        );

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

        // CAMS requires From/To as digits only (InvalidParameter.ToOnlyNumeric / FromOnlyNumeric).
        $to = $this->formatRecipient($toPhone);
        $from = $this->formatRecipient($from);
        if ($to === '') {
            return ['ok' => false, 'error' => 'Invalid WhatsApp recipient number.'];
        }
        if ($from === '') {
            return ['ok' => false, 'error' => 'WhatsApp sender FROM number is invalid.'];
        }

        $resolved = $this->resolveProviderTemplateCode(
            $templateCode,
            $language,
            $custSpaceId,
            $accessKeyId,
            $accessKeySecret,
        );
        if (! $resolved['ok']) {
            return ['ok' => false, 'error' => $resolved['error']];
        }
        $providerCode = (string) $resolved['code'];

        $payload = [
            'ChannelType' => 'whatsapp',
            'Type' => 'template',
            'TemplateCode' => $providerCode,
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
                'provider_code' => $providerCode,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'error' => 'WhatsApp request failed: '.$e->getMessage()];
        }

        $parsed = $this->parseCamsResponse($response);
        if (! $parsed['ok']) {
            Log::error('Operational WhatsApp alert delivery rejected', [
                'template' => $templateCode,
                'provider_code' => $providerCode,
                'to' => $to,
                'status' => $response->status(),
                'body' => $response->body(),
                'error' => $parsed['error'],
            ]);
        }

        return $parsed;
    }

    /**
     * CAMS SendChatappMessage needs a numeric TemplateCode. Config often stores the
     * Meta template name (snake_case) — resolve via ListChatappTemplate when needed.
     *
     * @return array{ok: bool, code: ?string, error: ?string}
     */
    private function resolveProviderTemplateCode(
        string $templateCodeOrName,
        string $language,
        string $custSpaceId,
        string $accessKeyId,
        string $accessKeySecret,
    ): array {
        $value = trim($templateCodeOrName);
        if ($value === '') {
            return ['ok' => false, 'code' => null, 'error' => 'WhatsApp template code is empty.'];
        }

        if (CamsTemplateIdentity::isProviderCode($value)) {
            return ['ok' => true, 'code' => $value, 'error' => null];
        }

        $cacheKey = 'ops-wa-template-code:'.hash('sha256', strtolower($custSpaceId.'|'.$language.'|'.$value));
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && CamsTemplateIdentity::isProviderCode($cached)) {
            return ['ok' => true, 'code' => $cached, 'error' => null];
        }

        $params = [
            'Action' => 'ListChatappTemplate',
            'Name' => $value,
            'Language' => $language,
            'PageSize' => '10',
            'PageIndex' => '1',
        ];
        if ($custSpaceId !== '') {
            $params['CustSpaceId'] = $custSpaceId;
        }

        try {
            $response = $this->signedRequest($params, $accessKeyId, $accessKeySecret);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'code' => null,
                'error' => 'Could not look up WhatsApp template "'.$value.'": '.$e->getMessage(),
            ];
        }

        $body = $response->json();
        $body = is_array($body) ? $body : [];
        if (! $response->successful()) {
            return [
                'ok' => false,
                'code' => null,
                'error' => $this->extractCamsError($body, $response->body(), $response->status()),
            ];
        }

        $code = Arr::get($body, 'Code') ?? Arr::get($body, 'code');
        if (is_string($code) && $code !== '' && strtoupper($code) !== 'OK') {
            return [
                'ok' => false,
                'code' => null,
                'error' => $this->extractCamsError($body, $response->body(), $response->status()),
            ];
        }

        $providerCode = $this->extractTemplateCodeFromList($body);
        if ($providerCode === null) {
            $spaceHint = $custSpaceId !== '' ? ' (CustSpaceId '.$custSpaceId.')' : '';

            return [
                'ok' => false,
                'code' => null,
                'error' => 'WhatsApp template "'.$value.'" was not found on this WhatsApp line'.$spaceHint.'. Use the exact approved template name, or set the numeric TemplateCode in env.',
            ];
        }

        Cache::put($cacheKey, $providerCode, now()->addHours(6));

        return ['ok' => true, 'code' => $providerCode, 'error' => null];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function extractTemplateCodeFromList(array $body): ?string
    {
        $lists = [
            Arr::get($body, 'ListTemplate'),
            Arr::get($body, 'Data.ListTemplate'),
            Arr::get($body, 'data.ListTemplate'),
            Arr::get($body, 'body.Data.ListTemplate'),
        ];

        foreach ($lists as $list) {
            if (! is_array($list) || $list === []) {
                continue;
            }

            $first = $list[0] ?? null;
            if (! is_array($first)) {
                continue;
            }

            $code = $first['TemplateCode'] ?? $first['templateCode'] ?? null;
            if (is_string($code) && CamsTemplateIdentity::isProviderCode($code)) {
                return $code;
            }
        }

        return null;
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
            return is_string($code) && $code !== '' ? trim($message).' ('.$code.')' : trim($message);
        }

        $trimmed = trim($rawBody);
        if ($trimmed !== '') {
            return 'WhatsApp provider rejected the message (HTTP '.$status.'): '.$trimmed;
        }

        return 'WhatsApp provider rejected the message (HTTP '.$status.').';
    }

    private function formatRecipient(string $phone): string
    {
        return PhoneNormalizer::normalize($phone)
            ?? (preg_replace('/\D+/', '', $phone) ?? '');
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

        // Action in $params overrides the default SendChatappMessage (e.g. ListChatappTemplate).
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
