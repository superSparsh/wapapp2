<?php

declare(strict_types=1);

namespace App\Domains\Api\Http\Controllers\V1;

use App\Domains\Api\Services\DirectMessageService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DirectMessageApiController extends Controller
{
    public function send(Request $request, DirectMessageService $service): JsonResponse
    {
        $validated = $request->validate([
            'template_uid' => ['required', 'string', 'max:64'],
            'to' => ['required', 'string', 'max:32'],
            'from' => ['nullable', 'string', 'max:32'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'variable_name' => ['nullable', 'array'],
            'variables' => ['nullable', 'array'],
            'template_variables' => ['nullable', 'array'],
        ]);

        // Keep free-form template vars from form posts (legacy clients).
        $payload = array_merge($request->all(), $validated);

        return response()->json($service->send($payload));
    }

    public function status(Request $request, DirectMessageService $service): JsonResponse
    {
        $validated = $request->validate([
            'message_id' => ['required', 'string', 'max:191'],
        ]);

        return response()->json($service->status((string) $validated['message_id']));
    }
}
