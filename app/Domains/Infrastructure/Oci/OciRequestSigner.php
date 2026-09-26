<?php

declare(strict_types=1);

namespace App\Domains\Infrastructure\Oci;

use RuntimeException;

/**
 * Oracle Cloud Infrastructure API request signer (RSA-SHA256).
 *
 * @see https://docs.oracle.com/en-us/iaas/Content/API/Concepts/signingrequests.htm
 */
final class OciRequestSigner
{
    public function __construct(
        private readonly string $tenancyOcid,
        private readonly string $userOcid,
        private readonly string $fingerprint,
        private readonly string $privateKeyPem,
        private readonly string $passphrase = '',
    ) {}

    /**
     * @param  array<string, string>  $extraHeaders
     * @return array<string, string>
     */
    public function sign(
        string $method,
        string $host,
        string $pathWithQuery,
        string $body = '',
        array $extraHeaders = [],
    ): array {
        $method = strtolower($method);
        $date = gmdate('D, d M Y H:i:s').' GMT';
        $headers = array_merge([
            'host' => $host,
            'date' => $date,
            'x-content-sha256' => base64_encode(hash('sha256', $body, true)),
            'content-type' => 'application/json',
            'content-length' => (string) strlen($body),
        ], $extraHeaders);

        $signingHeaders = ['(request-target)', 'host', 'date', 'x-content-sha256', 'content-type', 'content-length'];
        $lines = [];
        foreach ($signingHeaders as $name) {
            if ($name === '(request-target)') {
                $lines[] = '(request-target): '.$method.' '.$pathWithQuery;

                continue;
            }
            $lines[] = $name.': '.$headers[$name];
        }

        $signingString = implode("\n", $lines);
        $key = openssl_pkey_get_private($this->privateKeyPem, $this->passphrase !== '' ? $this->passphrase : null);
        if ($key === false) {
            throw new RuntimeException('Unable to load OCI private key for request signing.');
        }

        $ok = openssl_sign($signingString, $signature, $key, OPENSSL_ALGO_SHA256);
        if (! $ok) {
            throw new RuntimeException('OCI request signing failed.');
        }

        $keyId = $this->tenancyOcid.'/'.$this->userOcid.'/'.$this->fingerprint;
        $headers['authorization'] = sprintf(
            'Signature version="1",keyId="%s",algorithm="rsa-sha256",headers="%s",signature="%s"',
            $keyId,
            implode(' ', $signingHeaders),
            base64_encode($signature),
        );

        return $headers;
    }
}
