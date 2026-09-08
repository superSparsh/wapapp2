<?php

declare(strict_types=1);

namespace App\Domains\Templates\Http\Controllers;

use App\Domains\Templates\Services\AITemplateSuggestionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateAiController extends Controller
{
    public function suggestBodies(Request $request, AITemplateSuggestionService $service): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:500'],
            'context' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $suggestions = $service->suggestBodies(
                (string) $validated['prompt'],
                isset($validated['context']) ? (string) $validated['context'] : null,
            );

            return response()->json([
                'status' => 'success',
                'suggestions' => $suggestions,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
