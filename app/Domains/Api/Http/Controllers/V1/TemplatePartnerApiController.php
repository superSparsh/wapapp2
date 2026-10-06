<?php

declare(strict_types=1);

namespace App\Domains\Api\Http\Controllers\V1;

use App\Domains\Api\Support\PartnerTemplatePresenter;
use App\Domains\Templates\Enums\TemplateStatus;
use App\Http\Controllers\Controller;
use App\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplatePartnerApiController extends Controller
{
    public function index(Request $request, PartnerTemplatePresenter $presenter): JsonResponse
    {
        $templates = Template::query()
            ->where('status', TemplateStatus::Approved)
            ->when($request->filled('search'), function ($q) use ($request): void {
                $term = '%'.trim((string) $request->query('search')).'%';
                $q->where(function ($inner) use ($term): void {
                    $inner->where('name', 'like', $term)->orWhere('code', 'like', $term);
                });
            })
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Template $template): array => $presenter->present($template))
            ->values()
            ->all();

        // Legacy parity: bare JSON array (not {data, meta}).
        return response()->json($templates);
    }

    public function show(string $uid, PartnerTemplatePresenter $presenter): JsonResponse
    {
        $template = Template::query()->where('uuid', $uid)->first();

        if ($template === null) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        // Legacy parity: { "template": { ... } }
        return response()->json([
            'template' => $presenter->present($template),
        ]);
    }
}
