<?php

declare(strict_types=1);

namespace App\Repositories\Implementations;

use App\Models\Conversation;
use App\Repositories\Interfaces\ConversationRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class ConversationRepository implements ConversationRepositoryInterface
{
    public function findById(int $id): ?Conversation
    {
        return Conversation::query()->find($id);
    }

    public function findByUuid(string $uuid): ?Conversation
    {
        return Conversation::query()->where('uuid', $uuid)->first();
    }

    public function findByPhone(int $lineId, string $phone): ?Conversation
    {
        return Conversation::query()
            ->where('whatsapp_line_id', $lineId)
            ->where('contact_phone', $phone)
            ->first();
    }

    public function create(array $attributes): Conversation
    {
        return Conversation::query()->create($attributes);
    }

    public function update(Conversation $conversation, array $attributes): bool
    {
        return $conversation->update($attributes);
    }

    public function delete(Conversation $conversation): bool
    {
        return (bool) $conversation->delete();
    }

    public function query(): Builder
    {
        return Conversation::query();
    }
}
