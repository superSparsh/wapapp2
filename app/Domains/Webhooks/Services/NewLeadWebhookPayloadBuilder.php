<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Services;

use App\Models\WhatsappLine;
use App\Support\PhoneNormalizer;

/**
 * Builds outbound new_lead payloads (legacy WebhookService parity).
 */
final class NewLeadWebhookPayloadBuilder
{
    /**
     * @return array{phone: string, country_code: ?string, phone_e164: string}
     */
    public function parsePhone(string $raw): array
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return ['phone' => '', 'country_code' => null, 'phone_e164' => ''];
        }

        // Prefer India 10-digit / 91-prefixed forms (legacy fallback).
        if (strlen($digits) === 10) {
            return [
                'phone' => $digits,
                'country_code' => '+91',
                'phone_e164' => '91'.$digits,
            ];
        }

        if (strlen($digits) >= 11 && str_starts_with($digits, '91')) {
            $national = substr($digits, 2);

            return [
                'phone' => $national !== '' ? $national : $digits,
                'country_code' => '+91',
                'phone_e164' => $digits,
            ];
        }

        // Generic: treat leading 1–3 digits as country code when long enough.
        if (strlen($digits) > 10) {
            $ccLen = strlen($digits) - 10;
            if ($ccLen >= 1 && $ccLen <= 3) {
                $cc = substr($digits, 0, $ccLen);
                $national = substr($digits, $ccLen);

                return [
                    'phone' => $national,
                    'country_code' => '+'.$cc,
                    'phone_e164' => $digits,
                ];
            }
        }

        $normalized = PhoneNormalizer::normalize($digits) ?? $digits;

        return [
            'phone' => $digits,
            'country_code' => null,
            'phone_e164' => $normalized,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function businessLinePayload(WhatsappLine $line, string $to): array
    {
        $parsedTo = $this->parsePhone($to);
        $parsedLine = $this->parsePhone(trim((string) $line->phone));

        return [
            'to' => $parsedTo['phone_e164'],
            'received_on' => $parsedTo['phone_e164'],
            'business_phone' => $parsedLine['phone'],
            'business_country_code' => $parsedLine['country_code'],
            'business_line_id' => (int) $line->id,
            'business_line_name' => trim((string) ($line->display_name ?? '')) ?: null,
            'business_line' => [
                'id' => (int) $line->id,
                'phone' => $parsedLine['phone'],
                'country_code' => $parsedLine['country_code'],
                'phone_e164' => $parsedLine['phone_e164'],
                'verified_name' => trim((string) ($line->display_name ?? '')) ?: null,
                'is_default' => (bool) $line->is_default,
            ],
        ];
    }

    /**
     * @return array{event: string, data: array<string, mixed>, timestamp: int}
     */
    public function build(
        string $from,
        string $to,
        string $name,
        string $message,
        string|int|null $messageId,
        int|string|null $originalTimestamp,
        WhatsappLine $line,
    ): array {
        $parsedFrom = $this->parsePhone($from);
        $lineFields = $this->businessLinePayload($line, $to !== '' ? $to : (string) $line->phone);

        $createdAt = now()->toIso8601String();
        $timestamp = now()->timestamp;
        $original = $originalTimestamp ?? ($timestamp * 1000);

        return [
            'event' => 'new_lead',
            'data' => array_merge([
                'id' => $messageId !== null ? (string) $messageId : (string) $timestamp,
                'name' => $name,
                'phone' => $parsedFrom['phone'],
                'country_code' => $parsedFrom['country_code'],
                'phone_e164' => $parsedFrom['phone_e164'],
                'message' => $message,
                'created_at' => $createdAt,
                'original_timestamp' => is_numeric($original) ? (int) $original : $timestamp * 1000,
            ], $lineFields),
            'timestamp' => $timestamp,
        ];
    }

    /**
     * Sample payload for create-form Test Webhook / docs (legacy UI sample).
     *
     * @return array{event: string, data: array<string, mixed>, timestamp: int}
     */
    public function sample(?WhatsappLine $line = null): array
    {
        $line ??= WhatsappLine::query()->orderByDesc('is_default')->orderBy('id')->first();

        if ($line instanceof WhatsappLine) {
            return $this->build(
                from: '919876543210',
                to: (string) $line->phone,
                name: 'Test User',
                message: 'This is a test webhook payload',
                messageId: 'test_'.now()->timestamp,
                originalTimestamp: now()->timestamp * 1000,
                line: $line,
            );
        }

        return [
            'event' => 'new_lead',
            'data' => [
                'id' => 'test_'.now()->timestamp,
                'name' => 'Test User',
                'phone' => '9876543210',
                'country_code' => '+91',
                'phone_e164' => '919876543210',
                'message' => 'This is a test webhook payload',
                'created_at' => now()->toIso8601String(),
                'original_timestamp' => now()->getTimestampMs(),
                'to' => '919999999999',
                'received_on' => '919999999999',
                'business_phone' => '9999999999',
                'business_country_code' => '+91',
                'business_line_id' => 0,
                'business_line_name' => 'Test Line',
                'business_line' => [
                    'id' => 0,
                    'phone' => '9999999999',
                    'country_code' => '+91',
                    'phone_e164' => '919999999999',
                    'verified_name' => 'Test Line',
                    'is_default' => true,
                ],
            ],
            'timestamp' => now()->timestamp,
        ];
    }
}
