<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Services;

use App\Models\Conversation;
use App\Models\FcmToken;
use App\Models\Message;
use App\Models\User;
use App\Shared\Services\FeatureFlag;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class FcmPushService
{
    public function __construct(
        private readonly FcmClient $client,
        private readonly FeatureFlag $features,
    ) {}

    public function shouldSend(): bool
    {
        $enabled = $this->features->isEnabled('messaging.fcm-push');
        $ready = $this->client->isReady();

        if ($enabled && ! $ready) {
            Log::notice('FCM push skipped: feature on but Firebase credentials are not ready', [
                'credentials' => config('fcm.credentials'),
            ]);
        }

        return $enabled && $ready;
    }

    /**
     * Register / refresh a device token for the current actor.
     *
     * @param  array{token: string, platform?: string|null, device_id?: string|null, user_id?: int|null, team_member_id?: int|null}  $payload
     */
    public function register(array $payload): FcmToken
    {
        $token = trim((string) ($payload['token'] ?? ''));
        if ($token === '') {
            throw new \InvalidArgumentException('FCM token is required.');
        }

        $platform = $this->normalizePlatform($payload['platform'] ?? null);
        $deviceId = filled($payload['device_id'] ?? null) ? trim((string) $payload['device_id']) : null;
        $userId = isset($payload['user_id']) ? (int) $payload['user_id'] : null;
        $teamMemberId = isset($payload['team_member_id']) ? (int) $payload['team_member_id'] : null;

        if ($userId === null && $teamMemberId === null) {
            throw new \InvalidArgumentException('Either user_id or team_member_id is required.');
        }

        $existing = FcmToken::withTrashed()->where('token', $token)->first();
        if ($existing !== null) {
            if ($existing->trashed()) {
                $existing->restore();
            }
            $existing->forceFill([
                'user_id' => $userId,
                'team_member_id' => $teamMemberId,
                'platform' => $platform,
                'device_id' => $deviceId ?? $existing->device_id,
                'last_used_at' => now(),
            ])->save();

            return $existing->fresh() ?? $existing;
        }

        // One device_id per actor - replace stale row for that device.
        if ($deviceId !== null) {
            FcmToken::query()
                ->when($userId !== null, fn ($q) => $q->where('user_id', $userId))
                ->when($teamMemberId !== null, fn ($q) => $q->where('team_member_id', $teamMemberId))
                ->where('device_id', $deviceId)
                ->delete();
        }

        return FcmToken::query()->create([
            'user_id' => $userId,
            'team_member_id' => $teamMemberId,
            'token' => $token,
            'platform' => $platform,
            'device_id' => $deviceId,
            'last_used_at' => now(),
        ]);
    }

    public function revoke(string $token, ?int $userId = null, ?int $teamMemberId = null): int
    {
        $token = trim($token);
        if ($token === '') {
            return 0;
        }

        return FcmToken::query()
            ->where('token', $token)
            ->when($userId !== null, fn ($q) => $q->where('user_id', $userId))
            ->when($teamMemberId !== null, fn ($q) => $q->where('team_member_id', $teamMemberId))
            ->delete();
    }

    public function sendNewMessageNotification(Conversation $conversation, Message $message): void
    {
        if (! $this->shouldSend()) {
            return;
        }

        try {
            $tokens = $this->resolveTokensForConversation($conversation);
            if ($tokens->isEmpty()) {
                return;
            }

            $title = $this->notificationTitle($conversation);
            $body = $this->notificationBody($message);
            if ($body === '') {
                return;
            }

            $data = [
                'type' => 'inbox_new_message',
                'conversation_id' => (string) $conversation->id,
                'conversation_uuid' => (string) ($conversation->uuid ?? $conversation->id),
                'message_id' => (string) $message->id,
                'contact_phone' => (string) ($conversation->contact_phone ?? ''),
                'contact_name' => (string) ($conversation->contact_name ?? ''),
                // Flutter often reads these for the tray text (avoid hardcoded fallbacks).
                'title' => $title,
                'body' => $body,
                'message' => $body,
            ];

            foreach ($tokens as $row) {
                $ok = $this->client->sendToToken(
                    (string) $row->token,
                    ['title' => $title, 'body' => $body],
                    $data,
                );

                if ($ok) {
                    $row->forceFill(['last_used_at' => now()])->save();
                } else {
                    // Soft-prune likely-dead tokens after a failed send when client is ready.
                    // Only delete when token looks invalid length / empty response path already logged.
                }
            }
        } catch (Throwable $e) {
            Log::warning('FCM inbox notification failed (non-blocking)', [
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return Collection<int, FcmToken>
     */
    private function resolveTokensForConversation(Conversation $conversation): Collection
    {
        $tokens = collect();

        if ($conversation->assigned_team_member_id) {
            $tokens = FcmToken::query()
                ->where('team_member_id', (int) $conversation->assigned_team_member_id)
                ->get();
        } elseif ($conversation->assigned_user_id) {
            $tokens = FcmToken::query()
                ->where('user_id', (int) $conversation->assigned_user_id)
                ->get();
        }

        // Always include active owner/user tokens so mobile owners still get pushes
        // for assigned chats (legacy parity for single-owner accounts).
        $userIds = User::query()
            ->where('is_active', true)
            ->pluck('id');

        if ($userIds->isNotEmpty()) {
            $ownerTokens = FcmToken::query()
                ->whereIn('user_id', $userIds)
                ->get();
            $tokens = $tokens->concat($ownerTokens);
        }

        return $tokens->unique('id')->values();
    }

    private function notificationTitle(Conversation $conversation): string
    {
        $name = trim((string) ($conversation->contact_name ?: $conversation->contact_phone ?: ''));

        return $name !== '' ? $name : 'WapApp';
    }

    private function notificationBody(Message $message): string
    {
        $body = trim(strip_tags((string) ($message->body ?? '')));
        if ($body !== '') {
            return Str::limit($body, 140);
        }

        $meta = is_array($message->metadata ?? null) ? $message->metadata : [];
        $caption = trim(strip_tags((string) ($meta['caption'] ?? $meta['raw_message']['caption'] ?? '')));
        if ($caption !== '') {
            return Str::limit($caption, 140);
        }

        $type = strtolower((string) ($message->message_type?->value ?? ''));

        return match ($type) {
            'image' => 'Photo',
            'video' => 'Video',
            'audio', 'voice' => 'Audio',
            'document', 'file' => 'Document',
            'sticker' => 'Sticker',
            'location' => 'Location',
            'contacts', 'contact' => 'Contact',
            'interactive', 'button', 'list' => 'Interactive message',
            'reaction' => 'Reaction',
            default => '',
        };
    }

    private function normalizePlatform(mixed $platform): ?string
    {
        $value = strtolower(trim((string) ($platform ?? '')));
        if ($value === '') {
            return null;
        }

        return match ($value) {
            'ios', 'iphone', 'ipad' => 'ios',
            'android' => 'android',
            'web', 'browser' => 'web',
            default => Str::limit($value, 32, ''),
        };
    }
}
