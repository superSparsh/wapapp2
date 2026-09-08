<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\ServiceProvider;

class MacroServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Blueprint::macro('auditable', function (): void {
            /** @var Blueprint $this */
            $this->timestamps();
            $this->softDeletes();
        });

        Blueprint::macro('phoneNumber', function (string $column = 'phone', bool $unique = false, bool $nullable = false): void {
            /** @var Blueprint $this */
            $columnDefinition = $this->string($column, 20);

            if ($nullable) {
                $columnDefinition->nullable();
            }

            if ($unique) {
                $columnDefinition->unique();
            } else {
                $columnDefinition->index();
            }
        });

        Blueprint::macro('statusColumn', function (string $column = 'status', string $default = 'active'): void {
            /** @var Blueprint $this */
            $this->string($column, 32)->default($default)->index();
        });

        Blueprint::macro('publicUuid', function (string $column = 'uuid'): void {
            /** @var Blueprint $this */
            $this->uuid($column)->unique();
        });
    }
}
