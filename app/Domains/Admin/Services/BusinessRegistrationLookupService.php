<?php

declare(strict_types=1);

namespace App\Domains\Admin\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Resolve legal / trade business name from GSTIN or Udyam (MSME) registration numbers.
 *
 * Uses a configurable HTTP lookup endpoint so production can point at whatever
 * GST/MSME verification provider is available (no hard-coded third-party).
 */
class BusinessRegistrationLookupService
{
    /**
     * @return array{
     *     valid: bool,
     *     business_name: ?string,
     *     registration_number: string,
     *     doc_type: ?string,
     *     message: string
     * }
     */
    public function lookup(string $registrationNumber, ?string $docTypeHint = null): array
    {
        $raw = strtoupper(trim($registrationNumber));
        $raw = preg_replace('/\s+/', '', $raw) ?? $raw;

        $detected = $this->detectType($raw, $docTypeHint);
        if ($detected === null) {
            return [
                'valid' => false,
                'business_name' => null,
                'registration_number' => $raw,
                'doc_type' => null,
                'message' => 'Enter a valid GSTIN (15 characters) or Udyam MSME number (UDYAM-XX-00-0000000).',
            ];
        }

        ['doc_type' => $docType, 'normalized' => $normalized] = $detected;

        if (! $this->isFormatValid($normalized, $docType)) {
            return [
                'valid' => false,
                'business_name' => null,
                'registration_number' => $normalized,
                'doc_type' => $docType,
                'message' => $docType === 'GST certificate'
                    ? 'That GSTIN format looks incorrect. Double-check the 15-character number on your GST certificate.'
                    : 'That Udyam / MSME number format looks incorrect. Use the full UDYAM-XX-00-0000000 format.',
            ];
        }

        $endpoint = trim((string) config('customer-readiness.registration_lookup.url', ''));
        if ($endpoint === '') {
            return [
                'valid' => false,
                'business_name' => null,
                'registration_number' => $normalized,
                'doc_type' => $docType,
                'message' => 'We cannot verify registration numbers right now. Please try again later or contact support.',
            ];
        }

        try {
            $timeout = (int) config('customer-readiness.registration_lookup.timeout', 10);
            $apiKey = (string) config('customer-readiness.registration_lookup.api_key', '');
            $method = strtoupper((string) config('customer-readiness.registration_lookup.method', 'GET'));

            $headers = [
                'Accept' => 'application/json',
                'User-Agent' => 'WAPAPP-CustomerReadiness/1.0',
            ];
            if ($apiKey !== '') {
                $headers['Authorization'] = 'Bearer '.$apiKey;
                $headers['X-API-Key'] = $apiKey;
            }

            $query = [
                'registration_number' => $normalized,
                'gstin' => $normalized,
                'udyam' => $normalized,
                'type' => $docType === 'GST certificate' ? 'gst' : 'udyam',
            ];

            $request = Http::withHeaders($headers)
                ->connectTimeout(min(5, $timeout))
                ->timeout($timeout)
                ->retry(1, 200);

            $response = $method === 'POST'
                ? $request->asJson()->post($endpoint, $query)
                : $request->get($endpoint, $query);

            if (! $response->successful()) {
                Log::notice('Registration lookup HTTP non-success', [
                    'status' => $response->status(),
                    'registration_number' => $normalized,
                ]);

                return [
                    'valid' => false,
                    'business_name' => null,
                    'registration_number' => $normalized,
                    'doc_type' => $docType,
                    'message' => 'We could not verify this registration number with the registry. Please check the number and try again.',
                ];
            }

            $businessName = $this->extractBusinessName($response->json());
            if ($businessName === null || $businessName === '') {
                return [
                    'valid' => false,
                    'business_name' => null,
                    'registration_number' => $normalized,
                    'doc_type' => $docType,
                    'message' => 'No active business name was found for this registration number. We cannot onboard you until a valid GST / MSME registration is verified.',
                ];
            }

            return [
                'valid' => true,
                'business_name' => $businessName,
                'registration_number' => $normalized,
                'doc_type' => $docType,
                'message' => 'Business name fetched from registration records.',
            ];
        } catch (\Throwable $e) {
            Log::notice('Registration lookup failed', [
                'registration_number' => $normalized,
                'error' => $e->getMessage(),
            ]);

            return [
                'valid' => false,
                'business_name' => null,
                'registration_number' => $normalized,
                'doc_type' => $docType,
                'message' => 'Registration lookup is temporarily unavailable. Please try again in a moment.',
            ];
        }
    }

    /**
     * @return array{doc_type: string, normalized: string}|null
     */
    private function detectType(string $raw, ?string $docTypeHint): ?array
    {
        $hint = strtolower(trim((string) $docTypeHint));
        if (str_contains($hint, 'udyam') || str_contains($hint, 'msme') || str_starts_with($raw, 'UDYAM')) {
            return [
                'doc_type' => 'Udyam MSME certificate',
                'normalized' => $raw,
            ];
        }

        if (str_contains($hint, 'gst') || preg_match('/^[0-9]{2}[A-Z0-9]{13}$/', $raw) === 1) {
            return [
                'doc_type' => 'GST certificate',
                'normalized' => $raw,
            ];
        }

        if (preg_match('/^UDYAM-/i', $raw) === 1 || strlen($raw) === 15) {
            return [
                'doc_type' => strlen($raw) === 15 ? 'GST certificate' : 'Udyam MSME certificate',
                'normalized' => $raw,
            ];
        }

        return null;
    }

    private function isFormatValid(string $normalized, string $docType): bool
    {
        if ($docType === 'GST certificate') {
            // Standard GSTIN: 2 digit state + 10 char PAN + entity + Z + check digit
            return preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $normalized) === 1;
        }

        // UDYAM-XX-00-0000000 (state code, district, 7-digit serial)
        return preg_match('/^UDYAM-[A-Z]{2}-[0-9]{2}-[0-9]{7}$/', $normalized) === 1;
    }

    /**
     * @param  mixed  $json
     */
    private function extractBusinessName(mixed $json): ?string
    {
        if (! is_array($json)) {
            return null;
        }

        $candidates = [
            data_get($json, 'business_name'),
            data_get($json, 'businessName'),
            data_get($json, 'legal_name'),
            data_get($json, 'legalName'),
            data_get($json, 'trade_name'),
            data_get($json, 'tradeName'),
            data_get($json, 'data.business_name'),
            data_get($json, 'data.legal_name'),
            data_get($json, 'data.trade_name'),
            data_get($json, 'result.business_name'),
            data_get($json, 'result.legal_name'),
            data_get($json, 'taxpayerInfo.lgnm'),
            data_get($json, 'taxpayerInfo.tradeNam'),
            data_get($json, 'lgnm'),
            data_get($json, 'tradeNam'),
        ];

        foreach ($candidates as $candidate) {
            $name = trim((string) $candidate);
            if ($name !== '' && Str::lower($name) !== 'null') {
                return $name;
            }
        }

        return null;
    }
}
