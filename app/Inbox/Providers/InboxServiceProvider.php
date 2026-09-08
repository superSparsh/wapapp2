<?php

namespace App\Inbox\Providers;

use App\Inbox\Repositories\Implementations\ConversationRepository;
use App\Inbox\Repositories\Implementations\MessageRepository;
use App\Inbox\Repositories\Interfaces\ConversationRepositoryInterface;
use App\Inbox\Repositories\Interfaces\MessageRepositoryInterface;
use App\Inbox\Services\ConversationService;
use App\Inbox\Services\MessageService;
use Illuminate\Support\ServiceProvider;

class InboxServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind repositories
        $this->app->bind(ConversationRepositoryInterface::class, ConversationRepository::class);
        $this->app->bind(MessageRepositoryInterface::class, MessageRepository::class);
        
        // Bind services
        $this->app->bind(ConversationService::class, function ($app) {
            return new ConversationService(
                $app->make(ConversationRepositoryInterface::class),
                $app->make(MessageRepositoryInterface::class)
            );
        });
        
        $this->app->bind(MessageService::class, function ($app) {
            return new MessageService(
                $app->make(MessageRepositoryInterface::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
