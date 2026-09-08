<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Minimal Alibaba CAMS client for campaign outbound (simple + mass).
 */
class AlibabaCamsClient
{
    public function isConfigured(): bool
    {
        return filled(config('whatsapp.alibaba.access_key_id'))
            && filled(config('whatsapp.alibaba.access_key_secret'));
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function sendChatappMessage(array $params): Response
    {
        return $this->signedRequest(array_merge([
            'Action' => 'SendChatappMessage',
            'ChannelType' => 'whatsapp',
        ], $params));
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function sendChatappMassMessage(array $params): Response
    {
        return $this->signedRequest(array_merge([
            'Action' => 'SendChatappMassMessage',
            'ChannelType' => 'whatsapp',
            'Type' => 'template',
        ], $params));
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function signedRequest(array $params): Response
    {
        $accessKeyId = (string) config('whatsapp.alibaba.access_key_id');
        $accessKeySecret = (string) config('whatsapp.alibaba.access_key_secret');
        $endpoint = (string) config('whatsapp.alibaba.endpoint', 'cams.ap-southeast-1.aliyuncs.com');

        $common = [
            'Format' => 'JSON',
            'Version' => '2020-06-06',
            'AccessKeyId' => $accessKeyId,
            'SignatureMethod' => 'HMAC-SHA1',
            'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'SignatureVersion' => '1.0',
            'SignatureNonce' => bin2hex(random_bytes(8)),
        ];

        $query = $this->flattenQuery(array_merge($common, $params));
        ksort($query);

        $canonicalized = collect($query)
            ->map(fn (string $value, string $key) => $this->percentEncode($key).'='.$this->percentEncode($value))
            ->implode('&');

        $stringToSign = 'GET&%2F&'.$this->percentEncode($canonicalized);
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $accessKeySecret.'&', true));
        $query['Signature'] = $signature;

        return Http::timeout(20)->get("https://{$endpoint}/", $query);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, string>
     */
    private function flattenQuery(array $params, string $prefix = ''): array
    {
        $flat = [];

        foreach ($params as $key => $value) {
            $name = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                if ($value === []) {
                    continue;
                }

                if (array_is_list($value)) {
                    foreach ($value as $index => $item) {
                        $indexed = $name.'.'.($index + 1);
                        if (is_array($item)) {
                            $flat = array_merge($flat, $this->flattenQuery($item, $indexed));
                        } elseif ($item !== null && $item !== '') {
                            $flat[$indexed] = (string) $item;
                        }
                    }

                    continue;
                }

                $flat = array_merge($flat, $this->flattenQuery($value, $name));

                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            $flat[$name] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }

        return $flat;
    }

    private function percentEncode(string $value): string
    {
        return str_replace(['+', '*', '%7E'], ['%20', '%2A', '~'], rawurlencode($value));
    }
}
