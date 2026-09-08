<?php

declare(strict_types=1);

namespace App\Domains\Templates\Contracts;

interface TemplateServiceClientInterface
{
    public function isHealthy(): bool;

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function listTemplates(array $params = []): array;

    /**
     * @return array<string, mixed>|null
     */
    public function getTemplate(string $uuid): ?array;

    /**
     * @return list<array<string, mixed>>
     */
    public function getOptions(): array;

    /**
     * @return array<string, mixed>
     */
    public function preview(string $code): array;

    /**
     * @return array<string, mixed>
     */
    public function createDraft(): array;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createFromSetup(array $data): array;

    /**
     * @param array<string, mixed> $stepData
     * @return array<string, mixed>
     */
    public function saveStep(string $uuid, string $step, array $stepData): array;

    /**
     * @return array<string, mixed>
     */
    public function submit(string $uuid): array;

    public function deleteTemplate(string $uuid): bool;

    /**
     * @param list<string> $uuids
     */
    public function bulkDelete(array $uuids): int;

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function listVariables(array $params = []): array;

    /**
     * @return array{custom: list<array<string, mixed>>, builtin: list<array<string, mixed>>}
     */
    public function allVariables(): array;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createVariable(array $data): array;

    /**
     * @return array<string, mixed>|null
     */
    public function getVariable(string $uuid): ?array;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function updateVariable(string $uuid, array $data): array;

    public function deleteVariable(string $uuid): bool;
}
