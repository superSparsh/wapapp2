<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Contracts;

use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface CampaignServiceClientInterface
{
    public function isHealthy(): bool;

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function listCampaigns(array $params = []): array;

    /**
     * @return array<string, mixed>|null
     */
    public function getCampaign(string $uuid): ?array;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createCampaign(array $data): array;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function updateCampaign(string $uuid, array $data): array;

    public function deleteCampaign(string $uuid): bool;

    /**
     * @return array<string, mixed>
     */
    public function launchCampaign(string $uuid): array;

    /**
     * @return array<string, mixed>
     */
    public function toggleCampaign(string $uuid): array;

    /**
     * @return array<string, mixed>
     */
    public function duplicateCampaign(string $uuid): array;

    /**
     * @param array<string, mixed> $variables
     * @return array<string, mixed>
     */
    public function sendTestMessage(string $uuid, string $phone, array $variables = []): array;

    /**
     * @return array<string, mixed>
     */
    public function resendFailed(string $uuid): array;

    /**
     * @return array<string, mixed>
     */
    public function calculateCost(string $uuid, ?string $category = 'MARKETING'): array;

    /**
     * @return array<string, mixed>
     */
    public function importRecipients(string $uuid, UploadedFile $file): array;

    /**
     * @param array<int, array<string, mixed>> $recipients
     * @return array<string, mixed>
     */
    public function populateRecipients(string $uuid, array $recipients): array;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function storeWebhook(string $uuid, array $data): array;

    /**
     * @return array<string, mixed>
     */
    public function getStatistics(string $uuid): array;

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function getRecipients(string $uuid, array $params = []): array;

    public function exportRecipients(string $uuid): StreamedResponse;

    /**
     * @return array<string, mixed>
     */
    public function processDue(): array;
}
