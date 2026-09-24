<?php

declare(strict_types=1);

namespace App\Domains\Templates\Services;

use App\Domains\Templates\Support\VariableActorContext;
use App\Models\InteractiveMessage;
use App\Support\ListingSort;
use Illuminate\Support\Collection;

class InteractiveMessageService
{
    public function __construct(
        private readonly VariableActorContext $actorContext,
    ) {}

    /**
     * @return Collection<int, InteractiveMessage>
     */
    public function list(
        ?string $keyword = null,
        ?string $type = null,
        string $sort = 'updated_at',
        string $direction = 'desc',
    ): Collection {
        $query = InteractiveMessage::query();

        $keyword = trim((string) $keyword);
        if ($keyword !== '') {
            $query->where('name', 'like', '%'.$keyword.'%');
        }

        $type = trim((string) $type);
        if ($type !== '') {
            $query->where('type', $type);
        }

        ListingSort::apply($query, $sort, $direction, [
            'updated_at' => 'updated_at',
            'created_at' => 'created_at',
            'name' => 'name',
        ], 'updated_at');

        return $query->get();
    }

    /** @return list<string> */
    public function types(): array
    {
        return ['button', 'list', 'product', 'flow'];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): InteractiveMessage
    {
        return InteractiveMessage::query()->create([
            'name' => (string) ($data['name'] ?? 'Untitled Message'),
            'type' => (string) ($data['type'] ?? 'button'),
            'content' => $data['content'] ?? [],
            'whatsapp_line_id' => $this->actorContext->whatsappLineId(),
            'team_member_id' => $this->actorContext->teamMemberId(),
            'team_member_name' => $this->actorContext->teamMemberName(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(InteractiveMessage $message, array $data): InteractiveMessage
    {
        $message->update([
            'name' => (string) ($data['name'] ?? $message->name),
            'type' => (string) ($data['type'] ?? $message->type),
            'content' => $data['content'] ?? $message->content,
        ]);

        return $message->refresh();
    }

    public function delete(InteractiveMessage $message): void
    {
        $message->delete();
    }
}
