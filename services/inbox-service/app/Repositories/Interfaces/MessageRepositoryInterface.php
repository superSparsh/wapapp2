<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Models\Message;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface MessageRepositoryInterface
{
    public function findById(int $id): ?Message;

    public function findByUuid(string $uuid): ?Message;

    public function findByExternalId(string $externalId): ?Message;

    public function create(array $attributes): Message;

    public function update(Message $message, array $attributes): bool;

    public function delete(Message $message): bool;

    public function query(): Builder;
}
