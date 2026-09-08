<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request, GlobalSearchService $searchService): JsonResponse
    {
        $query = $request->string('q')->trim()->toString();

        return response()->json($searchService->search($query));
    }
}
