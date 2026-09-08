<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Contracts;

use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface InboxServiceClientInterface
{
    public function isHealthy(): bool;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getThreads(int $lineId, array $filters = []): array;

    public function getUnreadCount(?int $lineId = null): int;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getMessages(string $conversationUuid, array $filters = []): array;

    /**
     * @return array<string, mixed>
     */
    public function sendMessage(string $conversationUuid, string $body): array;

    /**
     * @return array<string, mixed>
     */
    public function sendMedia(
        string $conversationUuid,
        UploadedFile $file,
        string $mediaType,
        ?string $caption = null,
    ): array;

    /**
     * @param  array<string, mixed>  $templateParams
     * @return array<string, mixed>
     */
    public function sendTemplate(
        string $conversationUuid,
        string $templateCode,
        array $templateParams = [],
        ?string $language = null,
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function sendLocation(string $conversationUuid, float $latitude, float $longitude): array;

    /**
     * @return array<string, mixed>
     */
    public function sendSticker(string $conversationUuid, UploadedFile $file): array;

    public function markRead(string $conversationUuid): bool;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function markAllRead(int $lineId, array $filters = []): int;

    public function assign(
        string $conversationUuid,
        ?string $assigneeKey = null,
        ?int $userId = null,
        ?int $teamMemberId = null,
    ): array;

    public function toggleResponseType(string $conversationUuid, bool $aiEnabled): bool;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function toggleAllResponseType(int $lineId, bool $aiEnabled, array $filters = []): int;

    /**
     * @return array<string, mixed>
     */
    public function getWindowStatus(string $conversationUuid): array;

    /**
     * @return array<string, mixed>
     */
    public function storeContact(
        int $lineId,
        string $name,
        string $phone,
        ?string $linePhone = null,
        string $responseType = 'human_response',
        ?int $contactId = null,
    ): array;

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>
     */
    public function recordInbound(
        int $lineId,
        string $contactPhone,
        string $body,
        ?string $contactName = null,
        ?string $externalMessageId = null,
        string $messageType = 'text',
        ?string $linePhone = null,
        ?int $contactId = null,
        ?array $metadata = null,
    ): array;

    public function updateDeliveryStatus(
        string $externalMessageId,
        string $status,
        ?string $failedReason = null,
    ): bool;

    public function exportConversation(string $conversationUuid): StreamedResponse;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportAll(int $lineId, array $filters = []): StreamedResponse;
}
