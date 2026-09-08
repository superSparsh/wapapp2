<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Support;

/**
 * In-memory legacy → new ID remapping for one customer migration run.
 */
final class MigrationIdMap
{
    /** @var array<string, array<int|string, int|string>> */
    private array $maps = [];

    public function put(string $entity, int|string $legacyId, int|string $newId): void
    {
        $this->maps[$entity][(string) $legacyId] = $newId;
    }

    public function get(string $entity, int|string $legacyId): int|string|null
    {
        return $this->maps[$entity][(string) $legacyId] ?? null;
    }

    public function getInt(string $entity, int|string $legacyId): ?int
    {
        $value = $this->get($entity, $legacyId);

        return $value === null ? null : (int) $value;
    }

    /**
     * @return array<string, array<string, int|string>>
     */
    public function all(): array
    {
        return $this->maps;
    }
}
