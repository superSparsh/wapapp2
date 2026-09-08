<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Models\Conversation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface ConversationRepositoryInterface
{
    public function findById(int $id): ?Conversation;

    public function findByUuid(string $uuid): ?Conversation;

    public function findByPhone(int $lineId, string $phone): ?Conversation;

    public function create(array $attributes): Conversation;

    public function update(Conversation $conversation, array $attributes): bool;

    public function delete(Conversation $conversation): bool;

    public function query(): Builder;
}
