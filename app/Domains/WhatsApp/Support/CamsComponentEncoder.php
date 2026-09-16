<?php

declare(strict_types=1);

namespace App\Domains\WhatsApp\Support;

/**
 * Maps camelCase / lowercase template component keys to Alibaba CAMS RPC PascalCase
 * (Components.1.Type, Components.1.Buttons.1.Text, …) — same as the official SDK toMap().
 */
final class CamsComponentEncoder
{
    /** @var array<string, string> */
    private const KEY_MAP = [
        'type' => 'Type',
        'text' => 'Text',
        'format' => 'Format',
        'url' => 'Url',
        'fileName' => 'FileName',
        'fileType' => 'FileType',
        'thumbUrl' => 'ThumbUrl',
        'caption' => 'Caption',
        'buttons' => 'Buttons',
        'cards' => 'Cards',
        'cardComponents' => 'CardComponents',
        'hasExpiration' => 'HasExpiration',
        'codeExpirationMinutes' => 'CodeExpirationMinutes',
        'addSecretRecommendation' => 'AddSecretRecommendation',
        'duration' => 'Duration',
        'phoneNumber' => 'PhoneNumber',
        'flowAction' => 'FlowAction',
        'flowId' => 'FlowId',
        'navigateScreen' => 'NavigateScreen',
        'isOptOut' => 'IsOptOut',
        'couponCode' => 'CouponCode',
        'autofillText' => 'AutofillText',
        'zeroTapTermsAccepted' => 'ZeroTapTermsAccepted',
        'supportedApps' => 'SupportedApps',
        'packageName' => 'PackageName',
        'signatureHash' => 'SignatureHash',
        'urlType' => 'UrlType',
    ];

    /**
     * @param  list<array<string, mixed>>  $components
     * @return list<array<string, mixed>>
     */
    public static function forRpc(array $components): array
    {
        return array_values(array_map(
            static fn (array $component): array => self::encodeNode($component),
            $components,
        ));
    }

    /**
     * Legacy SDK shrink style: Components = json_encode(toMap components).
     *
     * @param  list<array<string, mixed>>  $components
     */
    public static function toJson(array $components): string
    {
        return json_encode(self::forRpc($components), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, mixed>  $example
     */
    public static function exampleToJson(array $example): string
    {
        return json_encode($example, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * Human-readable message from a raw CAMS error body / JSON string.
     */
    public static function friendlyError(?string $raw): string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return 'WhatsApp submission failed. Please try again.';
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return \Illuminate\Support\Str::limit($raw, 280);
        }

        $message = trim((string) ($decoded['Message'] ?? $decoded['message'] ?? ''));
        $code = trim((string) ($decoded['Code'] ?? $decoded['code'] ?? ''));

        if ($message === '' && $code === '') {
            return \Illuminate\Support\Str::limit($raw, 280);
        }

        $friendly = match (strtoupper($code)) {
            'MISSINGTYPE' => 'Template component Type is missing. Please re-check header/body/buttons and submit again.',
            'MISSINGCOMPONENTS' => 'Template components were not sent to WhatsApp. Please edit the template body and submit again.',
            'INVALIDPARAMETER', 'INVALIDPARAMETER.FORMAT' => $message !== '' ? $message : 'Invalid template parameter sent to WhatsApp.',
            'FORBIDDEN.RAM' => 'WhatsApp account permission error. Contact support.',
            default => $message !== '' ? $message : 'WhatsApp submission failed.',
        };

        if ($code !== '' && ! str_contains($friendly, $code)) {
            $friendly .= " ({$code})";
        }

        return \Illuminate\Support\Str::limit($friendly, 280);
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private static function encodeNode(array $node): array
    {
        $out = [];

        foreach ($node as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $rpcKey = self::KEY_MAP[$key] ?? (is_string($key) && preg_match('/^[A-Z]/', $key) ? $key : $key);

            // Already PascalCase from a previous pass
            if (! isset(self::KEY_MAP[$key]) && is_string($key) && preg_match('/^[A-Z]/', $key)) {
                $rpcKey = $key;
            } elseif (isset(self::KEY_MAP[$key])) {
                $rpcKey = self::KEY_MAP[$key];
            } else {
                // Unknown camelCase → PascalCase best-effort
                $rpcKey = ucfirst((string) $key);
            }

            if (is_array($value)) {
                if ($value === []) {
                    continue;
                }

                if (array_is_list($value)) {
                    $out[$rpcKey] = array_values(array_map(
                        static function (mixed $item): mixed {
                            return is_array($item) ? self::encodeNode($item) : $item;
                        },
                        $value,
                    ));
                } else {
                    $out[$rpcKey] = self::encodeNode($value);
                }

                continue;
            }

            if (is_bool($value)) {
                $out[$rpcKey] = $value;

                continue;
            }

            $out[$rpcKey] = $value;
        }

        return $out;
    }
}
