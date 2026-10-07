<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class BulkDestroy
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  callable(Model): void|null  $deleteEach
     */
    public static function byUuids(
        Request $request,
        string $modelClass,
        string $label,
        ?callable $deleteEach = null,
        string $uuidColumn = 'uuid',
    ): JsonResponse {
        $validated = $request->validate([
            'uuids' => ['required', 'array', 'min:1'],
            'uuids.*' => ['required', 'string'],
        ]);

        /** @var Builder $query */
        $query = $modelClass::query()->whereIn($uuidColumn, $validated['uuids']);
        /** @var Collection<int, Model> $items */
        $items = $query->get();

        $deleted = 0;
        foreach ($items as $item) {
            if ($deleteEach !== null) {
                $deleteEach($item);
            } else {
                $item->delete();
            }
            $deleted++;
        }

        return response()->json([
            'status' => 'success',
            'message' => "{$deleted} {$label} deleted.",
            'deleted' => $deleted,
            'total' => count($validated['uuids']),
        ]);
    }
}
