<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\OutboundTemplateSenderInterface;
use App\Repositories\Implementations\CampaignRecipientRepository;
use App\Repositories\Implementations\CampaignRepository;
use App\Repositories\Interfaces\CampaignRecipientRepositoryInterface;
use App\Repositories\Interfaces\CampaignRepositoryInterface;
use App\Services\Outbound\HttpInboxTemplateSender;
use App\Services\Outbound\LocalTemplateSender;
use App\Support\TenantContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class, function () {
            return new TenantContext;
        });

        $this->app->bind(CampaignRepositoryInterface::class, CampaignRepository::class);
        $this->app->bind(CampaignRecipientRepositoryInterface::class, CampaignRecipientRepository::class);

        // Bind Outbound sender based on environment
        $this->app->bind(OutboundTemplateSenderInterface::class, function ($app) {
            if ($app->environment('testing')) {
                return new LocalTemplateSender;
            }

            return new HttpInboxTemplateSender($app->make(TenantContext::class));
        });
    }

    public function boot(): void
    {
        //
    }
}
