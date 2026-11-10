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
     *     logical_name?: string|null,
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
        $logicalName = trim((string) ($sender['logical_name'] ?? ''));
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

            return [
                'ok' => false,
                'error' => $this->formatDeliveryError(
                    camsError: 'WhatsApp sender config missing (FROM number or Alibaba keys).',
                    template: $templateCode !== '' ? $templateCode : '(empty)',
                    providerCode: null,
                    language: $language,
                    from: $from,
                    to: $this->formatRecipient($toPhone),
                    custSpaceId: $custSpaceId,
                    params: $this->stringifyParams($templateParams),
                ),
                'message_id' => null,
            ];
        }

        // CAMS requires From/To as digits only (InvalidParameter.ToOnlyNumeric / FromOnlyNumeric).
        $to = $this->formatRecipient($toPhone);
        $from = $this->formatRecipient($from);
        if ($to === '') {
            return [
                'ok' => false,
                'error' => $this->formatDeliveryError(
                    camsError: 'Invalid WhatsApp recipient number.',
                    template: $templateCode,
                    providerCode: null,
                    language: $language,
                    from: $from,
                    to: $toPhone,
                    custSpaceId: $custSpaceId,
                    params: $this->stringifyParams($templateParams),
                ),
                'message_id' => null,
            ];
        }
        if ($from === '') {
            return [
                'ok' => false,
                'error' => $this->formatDeliveryError(
                    camsError: 'WhatsApp sender FROM number is invalid. Set OPERATIONAL_ALERTS_WA_FROM.',
                    template: $templateCode,
                    providerCode: null,
                    language: $language,
                    from: (string) ($sender['from'] ?? config('operational-alerts.whatsapp.from', '')),
                    to: $to,
                    custSpaceId: $custSpaceId,
                    params: $this->stringifyParams($templateParams),
                ),
                'message_id' => null,
            ];
        }

        if ($custSpaceId === '' && ! CamsTemplateIdentity::isProviderCode($templateCode)) {
            return [
                'ok' => false,
                'error' => $this->formatDeliveryError(
                    camsError: 'CustSpaceId is required to resolve template "'.$templateCode.'" (CAMS often returns InvalidParameter / must not be null when it is missing). Set OPERATIONAL_ALERTS_WA_CUST_SPACE_ID.',
                    template: $templateCode,
                    providerCode: null,
                    language: $language,
                    from: $from,
                    to: $to,
                    custSpaceId: $custSpaceId,
                    params: $this->stringifyParams($templateParams),
                ),
                'message_id' => null,
            ];
        }

        // 1) Live List by Meta/logical name on this CustSpaceId (avoids stale local codes)
        // 2) Fall back to resolving $templateCode (name or numeric)
        // 3) Last resort: explicit fallback_code from local Template row
        $lookupNames = array_values(array_unique(array_filter([
            $templateName,
            $logicalName,
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
            return [
                'ok' => false,
                'error' => $this->formatDeliveryError(
                    camsError: $resolved['error'] ?: 'WhatsApp template could not be resolved on this line.',
                    template: $templateCode,
                    providerCode: null,
                    language: $language,
                    from: $from,
                    to: $to,
                    custSpaceId: $custSpaceId,
                    params: $this->stringifyParams($templateParams),
                ),
                'message_id' => null,
            ];
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
                    // Cast to object so empty params encode as {} (not []), matching inbox/campaigns.
                    'TemplateParams' => json_encode((object) $this->stringifyParams($templateParams), JSON_THROW_ON_ERROR),
                ];

                if ($custSpaceId !== '') {
                    $payload['CustSpaceId'] = $custSpaceId;
                }

                try {
                    // Prefer POST form — GET query strings can mangle TemplateParams JSON
                    // (same reason AlibabaCamsClient uses signedFormPost for SendChatappMessage).
                    $response = $this->signedFormPost($payload, $accessKeyId, $accessKeySecret);
                } catch (\Throwable $e) {
                    Log::error('Operational WhatsApp alert request failed', [
                        'template' => $templateCode,
                        'provider_code' => $providerCode,
                        'language' => $tryLanguage,
                        'from' => $from,
                        'to' => $to,
                        'cust_space_id' => $custSpaceId !== '' ? $custSpaceId : null,
                        'params' => $this->stringifyParams($templateParams),
                        'error' => $e->getMessage(),
                    ]);

                    return [
                        'ok' => false,
                        'error' => $this->formatDeliveryError(
                            camsError: 'WhatsApp request failed: '.$e->getMessage(),
                            template: $templateCode,
                            providerCode: $providerCode,
                            language: $tryLanguage,
                            from: $from,
                            to: $to,
                            custSpaceId: $custSpaceId,
                            params: $this->stringifyParams($templateParams),
                        ),
                        'message_id' => null,
                    ];
                }

                $parsed = $this->parseCamsResponse($response);
                if ($parsed['ok']) {
                    return $parsed;
                }

                $lastParsed = $parsed;
                $errorText = strtolower((string) ($parsed['error'] ?? ''));
                // Wrong Language often surfaces as TemplateNotExist — try next locale / code.
                if (! str_contains($errorText, 'templatenotexist') && ! str_contains($errorText, 'template is not exist')) {
                    $detailed = $this->formatDeliveryError(
                        camsError: (string) ($parsed['error'] ?? 'WhatsApp API did not return success.'),
                        template: $templateCode,
                        providerCode: $providerCode,
                        language: $tryLanguage,
                        from: $from,
                        to: $to,
                        custSpaceId: $custSpaceId,
                        params: $this->stringifyParams($templateParams),
                    );
                    Log::error('Operational WhatsApp alert delivery rejected', [
                        'template' => $templateCode,
                        'provider_code' => $providerCode,
                        'language' => $tryLanguage,
                        'from' => $from,
                        'to' => $to,
                        'cust_space_id' => $custSpaceId !== '' ? $custSpaceId : null,
                        'params' => $this->stringifyParams($templateParams),
                        'error' => $parsed['error'] ?? null,
                    ]);

                    return ['ok' => false, 'error' => $detailed, 'message_id' => null];
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

        $finalError = $this->formatDeliveryError(
            camsError: (string) ($lastParsed['error'] ?? 'WhatsApp API did not return success.'),
            template: $templateCode,
            providerCode: implode(', ', $codesTried) ?: null,
            language: implode(', ', $languagesToTry),
            from: $from,
            to: $to,
            custSpaceId: $custSpaceId,
            params: $this->stringifyParams($templateParams),
        );

        Log::error('Operational WhatsApp alert delivery rejected', [
            'template' => $templateCode,
            'template_name' => $templateName !== '' ? $templateName : null,
            'provider_codes_tried' => $codesTried,
            'to' => $to,
            'languages_tried' => $languagesToTry,
            'params' => $this->stringifyParams($templateParams),
            'error' => $lastParsed['error'],
        ]);

        return ['ok' => false, 'error' => $finalError, 'message_id' => null];
    }

    /**
     * @param  array<string, string>  $params
     */
    private function formatDeliveryError(
        string $camsError,
        string $template,
        ?string $providerCode,
        string $language,
        string $from,
        string $to,
        string $custSpaceId,
        array $params,
    ): string {
        $paramParts = [];
        foreach ($params as $key => $value) {
            $paramParts[] = $key.'='.$value;
        }

        $lines = [
            'CAMS: '.$camsError,
            'Template: '.$template.($providerCode ? ' (code '.$providerCode.')' : ''),
            'Language: '.($language !== '' ? $language : 'n/a'),
            'From: '.($from !== '' ? $from : 'missing').' · To: '.($to !== '' ? $to : 'missing'),
            'CustSpaceId: '.($custSpaceId !== '' ? $custSpaceId : 'MISSING — set OPERATIONAL_ALERTS_WA_CUST_SPACE_ID'),
            'Params: '.($paramParts !== [] ? implode(', ', $paramParts) : '(none)'),
        ];

        return implode("\n", $lines);
    }

    private function forgetTemplateCodeCache(string $custSpaceId, string $language, string $name): void
    {
        $name = trim($name);
        if ($name === '') {
            return;
        }

        foreach ($this->templateNameLookupVariants($name) as $variant) {
            Cache::forget($this->templateCodeCacheKey($custSpaceId, $language, $variant));
            // Also clear pre-fix v1 keys that may hold a wrong fallback code.
            Cache::forget('ops-wa-template-code:'.hash('sha256', strtolower($custSpaceId.'|'.$language.'|'.$variant)));
        }
    }

    private function templateCodeCacheKey(string $custSpaceId, string $language, string $name): string
    {
        return 'ops-wa-template-code:v2:'.hash('sha256', strtolower($custSpaceId.'|'.$language.'|'.$name));
    }

    /**
     * @return list<string>
     */
    private function templateNameLookupVariants(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return [];
        }

        $variants = [$name];

        // "{customer_id}_{logical_name}" → also try logical short name.
        if (preg_match('/^\d+_(.+)$/', $name, $matches) === 1) {
            $variants[] = $matches[1];
        }

        // Prefixed / renamed rows: keep trailing google_calendar_* / calendly_* logical name.
        if (preg_match('/((?:google_calendar|calendly)_[a-z0-9_]+)$/i', $name, $matches) === 1) {
            $variants[] = $matches[1];
        }

        return array_values(array_unique(array_filter($variants)));
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

        $cacheKey = $this->templateCodeCacheKey($custSpaceId, $language, $value);
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

        $namesToFind = $this->templateNameLookupVariants($value);

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
        foreach ($namesToFind as $searchName) {
            foreach ($languagesToTry as $tryLanguage) {
                // 1) Exact Name filter
                // 2) Paginated unfiltered list — match exact / "{id}_{logical}" suffix only
                //    (never pick an unrelated first row — that caused create TemplateNotExist).
                foreach ([true, false] as $filterByName) {
                    $maxPages = $filterByName ? 1 : 5;
                    for ($page = 1; $page <= $maxPages; $page++) {
                        $params = [
                            'Action' => 'ListChatappTemplate',
                            'PageSize' => $filterByName ? '20' : '50',
                            'PageIndex' => (string) $page,
                        ];
                        if ($filterByName) {
                            $params['Name'] = $searchName;
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
                            $lastError = 'Could not look up WhatsApp template "'.$searchName.'": '.$e->getMessage();

                            break;
                        }

                        $body = $response->json();
                        $body = is_array($body) ? $body : [];
                        if (! $response->successful()) {
                            $lastError = $this->extractCamsError($body, $response->body(), $response->status());

                            break;
                        }

                        $code = Arr::get($body, 'Code') ?? Arr::get($body, 'code');
                        if (is_string($code) && $code !== '' && strtoupper($code) !== 'OK') {
                            $lastError = $this->extractCamsError($body, $response->body(), $response->status());

                            break;
                        }

                        $matched = $this->extractTemplateCodeFromList($body, $namesToFind);
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

                        $pageItems = $this->listTemplateItems($body);
                        if ($pageItems === [] || count($pageItems) < 50) {
                            break;
                        }
                    }
                }
            }
        }

        $spaceHint = $custSpaceId !== '' ? ' (CustSpaceId '.$custSpaceId.')' : '';

        return [
            'ok' => false,
            'code' => null,
            'language' => null,
            'error' => $lastError
                ?: ('WhatsApp template "'.$value.'" was not found on this WhatsApp line'.$spaceHint.'. Sync/refresh the template from the Templates page.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return list<array<string, mixed>>
     */
    private function listTemplateItems(array $body): array
    {
        foreach ([
            Arr::get($body, 'ListTemplate'),
            Arr::get($body, 'Data.ListTemplate'),
            Arr::get($body, 'data.ListTemplate'),
            Arr::get($body, 'body.Data.ListTemplate'),
        ] as $list) {
            if (is_array($list) && $list !== []) {
                return array_values(array_filter($list, static fn ($item): bool => is_array($item)));
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  list<string>|string|null  $preferredNames
     * @return array{code: string, language: ?string}|null
     */
    private function extractTemplateCodeFromList(array $body, array|string|null $preferredNames = null): ?array
    {
        $names = [];
        if (is_string($preferredNames) && trim($preferredNames) !== '') {
            $names = $this->templateNameLookupVariants($preferredNames);
        } elseif (is_array($preferredNames)) {
            foreach ($preferredNames as $preferredName) {
                if (is_string($preferredName) && trim($preferredName) !== '') {
                    foreach ($this->templateNameLookupVariants($preferredName) as $variant) {
                        $names[] = $variant;
                    }
                }
            }
            $names = array_values(array_unique($names));
        }

        $approvedMatch = null;
        $anyMatch = null;

        foreach ($this->listTemplateItems($body) as $item) {
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
            if ($names === []) {
                return $matched;
            }

            $isNameMatch = false;
            foreach ($names as $preferredName) {
                if ($name === $preferredName || str_ends_with($name, '_'.$preferredName)) {
                    $isNameMatch = true;
                    break;
                }
            }
            if (! $isNameMatch) {
                continue;
            }

            $audit = strtoupper(trim((string) ($item['AuditStatus'] ?? $item['auditStatus'] ?? '')));
            if (in_array($audit, ['APPROVED', 'PASS', ''], true)) {
                // Prefer explicitly approved; empty audit still accepted (some CAMS payloads omit it).
                if ($audit === 'APPROVED' || $audit === 'PASS') {
                    return $matched;
                }
                $approvedMatch ??= $matched;
            }

            $anyMatch ??= $matched;
        }

        return $approvedMatch ?? $anyMatch;
    }

    /**
     * CAMS rejects empty/null TemplateParams values with InvalidParameter
     * ("… must not be null"). Always send a non-empty string fallback.
     *
     * @param  array<string, string|int|float|null>  $params
     * @return array<string, string>
     */
    private function stringifyParams(array $params): array
    {
        $out = [];
        foreach ($params as $key => $value) {
            if ($value === null || is_array($value)) {
                $out[(string) $key] = 'N/A';

                continue;
            }

            $text = trim((string) $value);
            $out[(string) $key] = $text !== '' ? $text : 'N/A';
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
            ?? Arr::get($body, 'error.message')
            ?? Arr::get($body, 'AccessDeniedDetail.Message')
            ?? Arr::get($body, 'accessDeniedDetail.Message');

        $recommend = Arr::get($body, 'Recommend')
            ?? Arr::get($body, 'recommend')
            ?? Arr::get($body, 'AccessDeniedDetail.AuthAction')
            ?? Arr::get($body, 'AccessDeniedDetail.EncodedDiagnosticMessage');

        $code = Arr::get($body, 'Code')
            ?? Arr::get($body, 'code')
            ?? Arr::get($body, 'Data.Code');

        if (is_string($message) && trim($message) !== '') {
            $text = is_string($code) && $code !== '' ? trim($message).' ('.$code.')' : trim($message);
            if (is_string($recommend) && trim($recommend) !== '') {
                $text .= ' — '.$recommend;
            }

            return $text;
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

    /**
     * @param  array<string, string>  $params
     * @return array<string, string>
     */
    private function buildSignedQuery(array $params, string $accessKeyId, string $accessKeySecret, string $httpMethod): array
    {
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

        $stringToSign = strtoupper($httpMethod).'&%2F&'.$this->percentEncode($canonicalized);
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $accessKeySecret.'&', true));
        $query['Signature'] = $signature;

        return $query;
    }

    /** @param  array<string, string>  $params */
    private function signedRequest(array $params, string $accessKeyId, string $accessKeySecret): Response
    {
        $endpoint = (string) config('operational-alerts.alibaba.endpoint', 'cams.ap-southeast-1.aliyuncs.com');
        $query = $this->buildSignedQuery($params, $accessKeyId, $accessKeySecret, 'GET');

        return Http::timeout(15)->get("https://{$endpoint}/", $query);
    }

    /** @param  array<string, string>  $params */
    private function signedFormPost(array $params, string $accessKeyId, string $accessKeySecret): Response
    {
        $endpoint = (string) config('operational-alerts.alibaba.endpoint', 'cams.ap-southeast-1.aliyuncs.com');
        $query = $this->buildSignedQuery($params, $accessKeyId, $accessKeySecret, 'POST');

        return Http::asForm()->timeout(15)->post("https://{$endpoint}/", $query);
    }

    private function percentEncode(string $value): string
    {
        return str_replace(['+', '*', '%7E'], ['%20', '%2A', '~'], rawurlencode($value));
    }
}
