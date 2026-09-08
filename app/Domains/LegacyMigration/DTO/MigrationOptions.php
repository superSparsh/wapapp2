<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\DTO;

final class MigrationOptions
{
    /**
     * @param  list<string>|null  $onlyModules
     */
    public function __construct(
        public readonly bool $dryRun = false,
        public readonly bool $force = false,
        public readonly ?array $onlyModules = null,
        public readonly bool $skipInbox = false,
        public readonly bool $skipBilling = false,
    ) {}

    /**
     * @return list<string>
     */
    public function modules(): array
    {
        $all = config('legacy-migration.modules', []);

        if ($this->onlyModules !== null && $this->onlyModules !== []) {
            return array_values(array_intersect($all, $this->onlyModules));
        }

        $modules = $all;

        if ($this->skipInbox) {
            $modules = array_values(array_filter($modules, fn (string $m) => $m !== 'inbox'));
        }

        if ($this->skipBilling) {
            $modules = array_values(array_filter($modules, fn (string $m) => $m !== 'billing'));
        }

        return $modules;
    }
}
