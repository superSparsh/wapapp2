<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Services;

use App\Enums\MessageDirection;
use App\Enums\MessageType;
use App\Models\Message;

/**
 * Rewrite legacy inbound reactions stored as text / "[REACTION message]".
 * Requires metadata.raw_message (or a recoverable emoji body) — otherwise skipped.
 */
final class InboxReactionBackfillService
{
    /**
     * @return array{updated: bool, reason: string}
     */
    public function rewriteMessage(Message $message): array
    {
        if ($message->direction !== MessageDirection::Inbound) {
            return ['updated' => false, 'reason' => 'not_inbound'];
        }

        $metadata = is_array($message->metadata) ? $message->metadata : [];
        $alreadyOk = $message->message_type === MessageType::Reaction
            && (
                filled($metadata['reaction_emoji'] ?? null)
                || ! empty($metadata['reaction_removed'])
            );

        if ($alreadyOk) {
            return ['updated' => false, 'reason' => 'already_ok'];
        }

        if (! $this->isRewriteCandidate($message, $metadata)) {
            return ['updated' => false, 'reason' => 'not_candidate'];
        }

        $parsed = $this->parseFromStoredMessage($message, $metadata);
        if ($parsed === null) {
            return ['updated' => false, 'reason' => 'no_recoverable_payload'];
        }

        $message->forceFill([
            'body' => $parsed['body'],
            'message_type' => MessageType::Reaction,
            'metadata' => array_merge($metadata, $parsed['metadata']),
        ]);

        if ($message->exists) {
            $message->save();
        }

        return ['updated' => true, 'reason' => 'rewritten'];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function isRewriteCandidate(Message $message, array $metadata): bool
    {
        $body = trim((string) ($message->body ?? ''));

        if ($body === '[REACTION message]') {
            return true;
        }

        if ($message->message_type === MessageType::Reaction) {
            return true;
        }

        $raw = $metadata['raw_message'] ?? null;
        if (! is_array($raw)) {
            return false;
        }

        return $this->payloadLooksLikeReaction($raw);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{body: string, metadata: array<string, mixed>}|null
     */
    public function parseFromStoredMessage(Message $message, array $metadata): ?array
    {
        $raw = $metadata['raw_message'] ?? null;
        if (is_array($raw)) {
            $fromRaw = $this->extractReaction($raw);
            if ($fromRaw !== null) {
                return $fromRaw;
            }
        }

        $body = trim((string) ($message->body ?? ''));
        if ($body !== '' && $body !== '[REACTION message]' && ! str_starts_with($body, '[')) {
            return $this->extractReaction(['emoji' => $body]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function payloadLooksLikeReaction(array $payload): bool
    {
        if (isset($payload['reaction']) && is_array($payload['reaction'])) {
            return true;
        }

        if (strtolower((string) ($payload['type'] ?? '')) === 'reaction') {
            return true;
        }

        $hasEmojiKey = array_key_exists('emoji', $payload);
        $hasTarget = isset($payload['messageId'])
            || isset($payload['message_id'])
            || isset($payload['ContextMessageId'])
            || isset($payload['contextMessageId']);

        return $hasEmojiKey && $hasTarget;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{body: string, metadata: array<string, mixed>}|null
     */
    public function extractReaction(array $payload): ?array
    {
        $reaction = is_array($payload['reaction'] ?? null) ? $payload['reaction'] : $payload;

        $emoji = $this->firstNonEmptyString(
            $reaction['emoji'] ?? null,
            $payload['emoji'] ?? null,
        ) ?? '';

        // Empty emoji is valid only when payload clearly is a reaction (un-react).
        if ($emoji === '' && ! $this->payloadLooksLikeReaction($payload) && ! array_key_exists('emoji', $payload)) {
            return null;
        }

        $reactedMessageId = $this->firstNonEmptyString(
            $reaction['message_id'] ?? null,
            $reaction['messageId'] ?? null,
            $payload['message_id'] ?? null,
            $payload['messageId'] ?? null,
            $payload['ContextMessageId'] ?? null,
            $payload['contextMessageId'] ?? null,
        );

        $body = $emoji !== '' ? $emoji : 'Reaction removed';

        return [
            'body' => $body,
            'metadata' => array_filter([
                'reaction_emoji' => $emoji !== '' ? $emoji : null,
                'reacted_message_id' => $reactedMessageId,
                'reaction_removed' => $emoji === '' ? true : null,
                'raw_message' => $payload,
            ], static fn ($value) => $value !== null && $value !== []),
        ];
    }

    private function firstNonEmptyString(mixed ...$candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (! is_string($candidate) && ! is_numeric($candidate)) {
                continue;
            }
            $trimmed = trim((string) $candidate);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }
}
