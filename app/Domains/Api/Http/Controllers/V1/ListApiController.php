<?php

declare(strict_types=1);

namespace App\Domains\Api\Http\Controllers\V1;

use App\Domains\Audience\Services\MailListService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListApiController extends Controller
{
    public function index(Request $request, MailListService $mailListService): JsonResponse
    {
        $paginator = $mailListService->index(
            search: $request->query('search'),
            perPage: (int) $request->integer('per_page', 25),
        );

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
