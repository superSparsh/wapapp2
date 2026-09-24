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
            ->get(['uuid', 'name', 'status', 'rejection_reason'])
            ->map(function (Template $template): array {
                $isRejected = $template->status->value === 'rejected';
                $hasRejectionText = filled($template->rejection_reason);
                $error = ($isRejected || $hasRejectionText)
                    ? CamsComponentEncoder::presentError($template->rejection_reason)
                    : null;

                return [
                    'uuid' => $template->uuid,
                    'name' => (string) $template->name,
                    'status' => $template->status->label(),
                    'status_key' => $template->status->value,
                    'status_variant' => $template->status->chipVariant(),
                    'error' => $isRejected || $hasRejectionText,
                    'rejection_title' => $error['title'] ?? null,
                    'rejection_reason' => $error['message'] ?? $template->rejection_reason,
                    'rejection_hint' => $error['hint'] ?? null,
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
