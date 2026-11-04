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
     * @param  array{
     *     from?: string,
     *     cust_space_id?: string,
     *     language?: string,
     *     template_name?: string|null,
     *     fallback_code?: string|null
     * }|null  $sender
     * @return array{ok: bool, error: ?string, message_id: ?string}
     */
    public function sendTemplateResult(string $toPhone, string $templateCode, array $templateParams = [], ?array $sender = null): array
    {
        if (! (bool) config('operational-alerts.enabled', true)) {
            return ['ok' => false, 'error' => 'Operational WhatsApp alerts are disabled.', 'message_id' => null];
        }

        $accessKeyId = (string) config('operational-alerts.alibaba.access_key_id', '');
        $accessKeySecret = (string) config('operational-alerts.alibaba.access_key_secret', '');
        $from = trim((string) ($sender['from'] ?? config('operational-alerts.whatsapp.from', '')));
        $custSpaceId = trim((string) ($sender['cust_space_id'] ?? config('operational-alerts.whatsapp.cust_space_id', '')));
        $preferredLanguage = trim((string) ($sender['language'] ?? ''));
        $templateName = trim((string) ($sender['template_name'] ?? ''));
        $fallbackCode = trim((string) ($sender['fallback_code'] ?? ''));
        // Keep caller/template language as-is (including "en"). Only use ops default
        // when the caller did not specify a language — remapping en→en_GB here caused
        // DetailError.TemplateNotExist for Google/Calendly templates approved as "en".
        $language = $preferredLanguage !== ''
            ? $preferredLanguage
            : CamsTemplateIdentity::language(
                (string) config('operational-alerts.whatsapp.language', 'en_GB'),
            );

        if ($accessKeyId === '' || $accessKeySecret === '' || $from === '' || $templateCode === '') {
            Log::warning('Operational WhatsApp alert skipped: missing CAMS config', [
                'template' => $templateCode,
                'to' => $toPhone,
                'has_access_key' => $accessKeyId !== '',
                'has_secret' => $accessKeySecret !== '',
                'has_from' => $from !== '',
            ]);

            return ['ok' => false, 'error' => 'WhatsApp sender config missing (FROM number or Alibaba keys).', 'message_id' => null];
        }

        // CAMS requires From/To as digits only (InvalidParameter.ToOnlyNumeric / FromOnlyNumeric).
        $to = $this->formatRecipient($toPhone);
        $from = $this->formatRecipient($from);
        if ($to === '') {
            return ['ok' => false, 'error' => 'Invalid WhatsApp recipient number.', 'message_id' => null];
        }
        if ($from === '') {
            return ['ok' => false, 'error' => 'WhatsApp sender FROM number is invalid.', 'message_id' => null];
        }

        // 1) Live List by Meta/logical name on this CustSpaceId (avoids stale local codes)
        // 2) Fall back to resolving $templateCode (name or numeric)
        // 3) Last resort: explicit fallback_code from local Template row
        $lookupNames = array_values(array_unique(array_filter([
            $templateName,
            CamsTemplateIdentity::isProviderCode($templateCode) ? null : $templateCode,
        ])));

        $resolved = ['ok' => false, 'code' => null, 'language' => null, 'error' => null];
        foreach ($lookupNames as $lookupName) {
            $resolved = $this->resolveProviderTemplateCode(
                $lookupName,
                $language,
                $custSpaceId,
                $accessKeyId,
                $accessKeySecret,
            );
            if ($resolved['ok']) {
                break;
            }
        }

        if (! $resolved['ok'] && CamsTemplateIdentity::isProviderCode($templateCode)) {
            $resolved = ['ok' => true, 'code' => $templateCode, 'language' => $language, 'error' => null];
        }

        if (! $resolved['ok'] && CamsTemplateIdentity::isProviderCode($fallbackCode)) {
            $resolved = ['ok' => true, 'code' => $fallbackCode, 'language' => $language, 'error' => null];
        }

        if (! $resolved['ok']) {
            return ['ok' => false, 'error' => $resolved['error'] ?: 'WhatsApp template could not be resolved on this line.', 'message_id' => null];
        }

        $providerCodesToTry = array_values(array_unique(array_filter([
            (string) $resolved['code'],
            CamsTemplateIdentity::isProviderCode($fallbackCode) ? $fallbackCode : null,
            CamsTemplateIdentity::isProviderCode($templateCode) ? $templateCode : null,
        ])));

        $sendLanguage = trim((string) ($resolved['language'] ?? '')) !== ''
            ? (string) $resolved['language']
            : $language;

        $languagesToTry = array_values(array_unique(array_filter([
            $sendLanguage,
            $preferredLanguage,
            'en',
            'en_US',
            'en_GB',
        ], static fn ($lang): bool => is_string($lang) && trim($lang) !== '')));

        $lastParsed = ['ok' => false, 'error' => 'WhatsApp API did not return success.', 'message_id' => null];
        $codesTried = [];
        $didRefreshList = false;

        while ($providerCodesToTry !== []) {
            $providerCode = array_shift($providerCodesToTry);
            if (! is_string($providerCode) || $providerCode === '' || in_array($providerCode, $codesTried, true)) {
                continue;
            }
            $codesTried[] = $providerCode;

            $hitTemplateNotExist = false;
            foreach ($languagesToTry as $tryLanguage) {
                $payload = [
                    'ChannelType' => 'whatsapp',
                    'Type' => 'template',
                    'TemplateCode' => $providerCode,
                    'Language' => $tryLanguage,
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
                        'language' => $tryLanguage,
                        'to' => $to,
                        'error' => $e->getMessage(),
                    ]);

                    return ['ok' => false, 'error' => 'WhatsApp request failed: '.$e->getMessage(), 'message_id' => null];
                }

                $parsed = $this->parseCamsResponse($response);
                if ($parsed['ok']) {
                    return $parsed;
                }

                $lastParsed = $parsed;
                $errorText = strtolower((string) ($parsed['error'] ?? ''));
                // Wrong Language often surfaces as TemplateNotExist — try next locale / code.
                if (! str_contains($errorText, 'templatenotexist') && ! str_contains($errorText, 'template is not exist')) {
                    Log::error('Operational WhatsApp alert delivery rejected', [
                        'template' => $templateCode,
                        'provider_code' => $providerCode,
                        'to' => $to,
                        'error' => $lastParsed['error'],
                    ]);

                    return $lastParsed;
                }

                $hitTemplateNotExist = true;
            }

            // Stale cached/local code: bust List cache once and queue a fresh TemplateCode.
            if ($hitTemplateNotExist && ! $didRefreshList && $lookupNames !== []) {
                $didRefreshList = true;
                foreach ($lookupNames as $lookupName) {
                    $this->forgetTemplateCodeCache($custSpaceId, $language, $lookupName);
                    $fresh = $this->resolveProviderTemplateCode(
                        $lookupName,
                        $language,
                        $custSpaceId,
                        $accessKeyId,
                        $accessKeySecret,
                    );
                    if ($fresh['ok']
                        && is_string($fresh['code'])
                        && ! in_array($fresh['code'], $codesTried, true)
                    ) {
                        $providerCodesToTry[] = $fresh['code'];
                        if (trim((string) ($fresh['language'] ?? '')) !== '') {
                            array_unshift($languagesToTry, (string) $fresh['language']);
                            $languagesToTry = array_values(array_unique($languagesToTry));
                        }
                        break;
                    }
                }
            }
        }

        Log::error('Operational WhatsApp alert delivery rejected', [
            'template' => $templateCode,
            'template_name' => $templateName !== '' ? $templateName : null,
            'provider_codes_tried' => $codesTried,
            'to' => $to,
            'languages_tried' => $languagesToTry,
            'error' => $lastParsed['error'],
        ]);

        return $lastParsed;
    }

    private function forgetTemplateCodeCache(string $custSpaceId, string $language, string $name): void
    {
        $name = trim($name);
        if ($name === '') {
            return;
        }

        Cache::forget('ops-wa-template-code:'.hash('sha256', strtolower($custSpaceId.'|'.$language.'|'.$name)));
    }

    /**
     * CAMS SendChatappMessage needs a numeric TemplateCode. Config often stores the
     * Meta template name (snake_case) — resolve via ListChatappTemplate when needed.
     *
     * @return array{ok: bool, code: ?string, language: ?string, error: ?string}
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
            return ['ok' => false, 'code' => null, 'language' => null, 'error' => 'WhatsApp template code is empty.'];
        }

        if (CamsTemplateIdentity::isProviderCode($value)) {
            return ['ok' => true, 'code' => $value, 'language' => $language, 'error' => null];
        }

        $cacheKey = 'ops-wa-template-code:'.hash('sha256', strtolower($custSpaceId.'|'.$language.'|'.$value));
        $cached = Cache::get($cacheKey);
        if (is_array($cached)
            && is_string($cached['code'] ?? null)
            && CamsTemplateIdentity::isProviderCode($cached['code'])
        ) {
            return [
                'ok' => true,
                'code' => $cached['code'],
                'language' => is_string($cached['language'] ?? null) ? $cached['language'] : $language,
                'error' => null,
            ];
        }
        // Legacy cache entries stored only the code string.
        if (is_string($cached) && CamsTemplateIdentity::isProviderCode($cached)) {
            return ['ok' => true, 'code' => $cached, 'language' => $language, 'error' => null];
        }

        // Try configured language first, then fall back without Language filter.
        // Approved Google/Calendly templates are often en / en_US while ops default is en_GB.
        $languagesToTry = array_values(array_unique(array_filter([
            $language,
            'en',
            'en_US',
            'en_GB',
            '',
        ], static fn ($lang): bool => is_string($lang))));

        $lastError = null;
        foreach ($languagesToTry as $tryLanguage) {
            // 1) Exact Name filter (short logical name)
            // 2) Unfiltered page — match "{customer_id}_{logical_name}" Meta real names
            foreach ([true, false] as $filterByName) {
                $params = [
                    'Action' => 'ListChatappTemplate',
                    'PageSize' => $filterByName ? '10' : '50',
                    'PageIndex' => '1',
                ];
                if ($filterByName) {
                    $params['Name'] = $value;
                }
                if ($tryLanguage !== '') {
                    $params['Language'] = $tryLanguage;
                }
                if ($custSpaceId !== '') {
                    $params['CustSpaceId'] = $custSpaceId;
                }

                try {
                    $response = $this->signedRequest($params, $accessKeyId, $accessKeySecret);
                } catch (\Throwable $e) {
                    $lastError = 'Could not look up WhatsApp template "'.$value.'": '.$e->getMessage();

                    continue;
                }

                $body = $response->json();
                $body = is_array($body) ? $body : [];
                if (! $response->successful()) {
                    $lastError = $this->extractCamsError($body, $response->body(), $response->status());

                    continue;
                }

                $code = Arr::get($body, 'Code') ?? Arr::get($body, 'code');
                if (is_string($code) && $code !== '' && strtoupper($code) !== 'OK') {
                    $lastError = $this->extractCamsError($body, $response->body(), $response->status());

                    continue;
                }

                $matched = $this->extractTemplateCodeFromList($body, $value);
                if ($matched !== null) {
                    $matchedLanguage = trim((string) ($matched['language'] ?? ''));
                    if ($matchedLanguage === '') {
                        $matchedLanguage = $tryLanguage !== '' ? $tryLanguage : $language;
                    }
                    Cache::put($cacheKey, [
                        'code' => $matched['code'],
                        'language' => $matchedLanguage,
                    ], now()->addHours(6));

                    return [
                        'ok' => true,
                        'code' => $matched['code'],
                        'language' => $matchedLanguage,
                        'error' => null,
                    ];
                }
            }
        }

        $spaceHint = $custSpaceId !== '' ? ' (CustSpaceId '.$custSpaceId.')' : '';

        return [
            'ok' => false,
            'code' => null,
            'language' => null,
            'error' => $lastError
                ?: ('WhatsApp template "'.$value.'" was not found on this WhatsApp line'.$spaceHint.'. Use the exact approved template name, or set the numeric TemplateCode in env.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{code: string, language: ?string}|null
     */
    private function extractTemplateCodeFromList(array $body, ?string $preferredName = null): ?array
    {
        $lists = [
            Arr::get($body, 'ListTemplate'),
            Arr::get($body, 'Data.ListTemplate'),
            Arr::get($body, 'data.ListTemplate'),
            Arr::get($body, 'body.Data.ListTemplate'),
        ];

        $preferredName = is_string($preferredName) ? trim($preferredName) : '';
        $fallback = null;

        foreach ($lists as $list) {
            if (! is_array($list) || $list === []) {
                continue;
            }

            foreach ($list as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $code = $item['TemplateCode'] ?? $item['templateCode'] ?? null;
                if (! is_string($code) || ! CamsTemplateIdentity::isProviderCode($code)) {
                    continue;
                }

                $itemLanguage = trim((string) ($item['Language'] ?? $item['language'] ?? ''));
                $matched = [
                    'code' => $code,
                    'language' => $itemLanguage !== '' ? $itemLanguage : null,
                ];

                $name = trim((string) ($item['TemplateName'] ?? $item['templateName'] ?? ''));
                if ($preferredName !== '') {
                    if ($name === $preferredName || str_ends_with($name, '_'.$preferredName)) {
                        return $matched;
                    }
                    $fallback ??= $matched;

                    continue;
                }

                return $matched;
            }
        }

        return $fallback;
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
     * @return array{ok: bool, error: ?string, message_id: ?string}
     */
    private function parseCamsResponse(Response $response): array
    {
        $body = $response->json();
        $body = is_array($body) ? $body : [];

        if (! $response->successful()) {
            return [
                'ok' => false,
                'error' => $this->extractCamsError($body, $response->body(), $response->status()),
                'message_id' => null,
            ];
        }

        $code = Arr::get($body, 'Code')
            ?? Arr::get($body, 'code')
            ?? Arr::get($body, 'Data.Code')
            ?? Arr::get($body, 'data.Code');

        if (is_string($code) && $code !== '' && strtoupper($code) !== 'OK') {
            return [
                'ok' => false,
                'error' => $this->extractCamsError($body, $response->body(), $response->status()),
                'message_id' => null,
            ];
        }

        $messageId = Arr::get($body, 'MessageId')
            ?? Arr::get($body, 'messageId')
            ?? Arr::get($body, 'Data.MessageId')
            ?? Arr::get($body, 'data.MessageId')
            ?? Arr::get($body, 'Data.messageId')
            ?? Arr::get($body, 'data.messageId');

        $messageId = is_string($messageId) || is_numeric($messageId) ? trim((string) $messageId) : '';

        return [
            'ok' => true,
            'error' => null,
            'message_id' => $messageId !== '' ? $messageId : null,
        ];
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
