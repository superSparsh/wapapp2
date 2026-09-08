<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Implementations\TemplateRepository;
use App\Repositories\Implementations\VariableRepository;
use App\Repositories\Interfaces\TemplateRepositoryInterface;
use App\Repositories\Interfaces\VariableRepositoryInterface;
use App\Support\TenantContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class, function () {
            return new TenantContext();
        });

        $this->app->bind(
            TemplateRepositoryInterface::class,
            TemplateRepository::class,
        );

        $this->app->bind(
            VariableRepositoryInterface::class,
            VariableRepository::class,
        );
    }

    public function boot(): void
    {
        //
    }
}
