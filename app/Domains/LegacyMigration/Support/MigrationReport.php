<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Support;

final class MigrationReport
{
    /** @var array<string, array{created: int, updated: int, skipped: int, failed: int}> */
    private array $modules = [];

    /** @var list<string> */
    private array $warnings = [];

    /** @var list<string> */
    private array $errors = [];

    public function bump(string $module, string $outcome, int $by = 1): void
    {
        if (! isset($this->modules[$module])) {
            $this->modules[$module] = [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'failed' => 0,
            ];
        }

        if (! array_key_exists($outcome, $this->modules[$module])) {
            $outcome = 'failed';
        }

        $this->modules[$module][$outcome] += $by;
    }

    public function warn(string $message): void
    {
        $this->warnings[] = $message;
    }

    public function error(string $message): void
    {
        $this->errors[] = $message;
    }

    /**
     * @return array{
     *     modules: array<string, array{created: int, updated: int, skipped: int, failed: int}>,
     *     warnings: list<string>,
     *     errors: list<string>,
     *     totals: array{created: int, updated: int, skipped: int, failed: int}
     * }
     */
    public function toArray(): array
    {
        $totals = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($this->modules as $stats) {
            foreach ($totals as $key => $_) {
                $totals[$key] += $stats[$key];
            }
        }

        return [
            'modules' => $this->modules,
            'warnings' => $this->warnings,
            'errors' => $this->errors,
            'totals' => $totals,
        ];
    }
}
