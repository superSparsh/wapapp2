<?php

declare(strict_types=1);

namespace App\Domains\Infrastructure\Oci\Contracts;

interface OciContainerInstanceClient
{
    /**
     * Create a campaign Horizon worker Container Instance.
     *
     * @param  array<string, string>  $environment
     * @return array{ocid: string, display_name: string}
     */
    public function createCampaignWorker(string $displayName, array $environment = []): array;

    /**
     * Delete a Container Instance by OCID (idempotent if already gone).
     */
    public function delete(string $ocid): void;

    /**
     * Whether the client can talk to OCI (credentials / config present).
     */
    public function isConfigured(): bool;
}
