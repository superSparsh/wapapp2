<?php

use App\Domains\Auth\Services\TenantResolver;
use App\Domains\Inbox\Services\InboxAccessService;
use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('inbox.{tenantId}', function ($user, string $tenantId) {
    if (! tenancy()->initialized) {
        app(TenantResolver::class)->initializeFromSession();
    }

    return $user !== null
        && tenancy()->initialized
        && tenant('id') === $tenantId;
});

Broadcast::channel('inbox.{tenantId}.conversation.{conversationUuid}', function ($user, string $tenantId, string $conversationUuid) {
    if (! tenancy()->initialized) {
        app(TenantResolver::class)->initializeFromSession();
    }

    if ($user === null || ! tenancy()->initialized || tenant('id') !== $tenantId) {
        return false;
    }

    $conversation = Conversation::query()->where('uuid', $conversationUuid)->first();

    if ($conversation === null) {
        return false;
    }

    return app(InboxAccessService::class)->canAccessConversation($conversation);
});
