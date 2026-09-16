<?php

declare(strict_types=1);

namespace App\Domains\Templates\Http\Controllers;

use App\Domains\Templates\Services\TemplateRegistryService;
use App\Domains\Templates\Services\TemplateServiceAdapter;
use App\Domains\WhatsApp\Support\CamsComponentEncoder;
use App\Http\Controllers\Controller;
use App\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function statuses(Request $request): JsonResponse
    {
        $uuids = collect($request->input('uuids', []))
            ->filter(fn ($uuid) => is_string($uuid) && $uuid !== '')
            ->unique()
            ->values()
            ->all();

        if ($uuids === []) {
            return response()->json(['items' => []]);
        }

        $items = Template::query()
            ->whereIn('uuid', $uuids)
            ->get(['uuid', 'status', 'rejection_reason'])
            ->map(function (Template $template): array {
                $isRejected = $template->status->value === 'rejected';

                return [
                    'uuid' => $template->uuid,
                    'status' => $template->status->label(),
                    'status_variant' => $template->status->chipVariant(),
                    'error' => $isRejected,
                    'rejection_reason' => $isRejected
                        ? CamsComponentEncoder::friendlyError($template->rejection_reason)
                        : null,
                ];
            })
            ->values()
            ->all();

        return response()->json(['items' => $items]);
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
