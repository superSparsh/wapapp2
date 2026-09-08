<?php

declare(strict_types=1);

namespace App\Repositories\Implementations;

use App\Models\Message;
use App\Repositories\Interfaces\MessageRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class MessageRepository implements MessageRepositoryInterface
{
    public function findById(int $id): ?Message
    {
        return Message::query()->find($id);
    }

    public function findByUuid(string $uuid): ?Message
    {
        return Message::query()->where('uuid', $uuid)->first();
    }

    public function findByExternalId(string $externalId): ?Message
    {
        return Message::query()->where('external_message_id', $externalId)->first();
    }

    public function create(array $attributes): Message
    {
        return Message::query()->create($attributes);
    }

    public function update(Message $message, array $attributes): bool
    {
        return $message->update($attributes);
    }

    public function delete(Message $message): bool
    {
        return (bool) $message->delete();
    }

    public function query(): Builder
    {
        return Message::query();
    }
}
