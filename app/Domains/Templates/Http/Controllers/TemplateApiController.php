<?php

declare(strict_types=1);

namespace App\Domains\Templates\Http\Controllers;

use App\Domains\Templates\Services\TemplateRegistryService;
use App\Domains\Templates\Services\TemplateServiceAdapter;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class TemplateApiController extends Controller
{
    public function __construct(
        private readonly TemplateServiceAdapter $adapter,
    ) {}

    public function index(TemplateRegistryService $registry): JsonResponse
    {
        return response()->json([
            'items' => $this->adapter->options(),
        ]);
    }

    public function refresh(TemplateRegistryService $registry): JsonResponse
    {
        return response()->json([
            'synced' => $registry->refresh(),
        ]);
    }

    public function preview(string $code): JsonResponse
    {
        return response()->json($this->adapter->preview($code));
    }

    public function variables(): JsonResponse
    {
        return response()->json($this->adapter->variablesData());
    }
}
