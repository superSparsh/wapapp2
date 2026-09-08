<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Implementations\ConversationRepository;
use App\Repositories\Implementations\MessageRepository;
use App\Repositories\Interfaces\ConversationRepositoryInterface;
use App\Repositories\Interfaces\MessageRepositoryInterface;
use App\Services\Contracts\OutboundMessageGateway;
use App\Services\Outbound\LocalOutboundMessageGateway;
use App\Support\TenantContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class, fn () => new TenantContext());

        $this->app->bind(ConversationRepositoryInterface::class, ConversationRepository::class);
        $this->app->bind(MessageRepositoryInterface::class, MessageRepository::class);
        $this->app->bind(OutboundMessageGateway::class, LocalOutboundMessageGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
