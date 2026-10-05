<?php

declare(strict_types=1);

namespace App\Domains\Api\Http\Controllers\V1;

use App\Domains\Templates\Enums\TemplateStatus;
use App\Http\Controllers\Controller;
use App\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplatePartnerApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->integer('per_page', 25)));

        $paginator = Template::query()
            ->where('status', TemplateStatus::Approved)
            ->when($request->filled('search'), function ($q) use ($request): void {
                $term = '%'.trim((string) $request->query('search')).'%';
                $q->where(function ($inner) use ($term): void {
                    $inner->where('name', 'like', $term)->orWhere('code', 'like', $term);
                });
            })
            ->orderByDesc('updated_at')
            ->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())->map(fn (Template $t): array => $this->serialize($t))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function show(string $uid): JsonResponse
    {
        $template = Template::query()->where('uuid', $uid)->first();
        abort_if($template === null, 404, 'Template not found.');

        return response()->json([
            'success' => true,
            'data' => $this->serialize($template),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Template $template): array
    {
        return [
            'uid' => $template->uuid,
            'name' => $template->name,
            'code' => $template->whatsappCode() ?? $template->code,
            'language' => $template->language,
            'category' => $template->category,
            'status' => $template->status instanceof TemplateStatus
                ? $template->status->value
                : (string) $template->status,
            'body_preview' => $template->body_preview,
            'created_at' => optional($template->created_at)?->toIso8601String(),
            'updated_at' => optional($template->updated_at)?->toIso8601String(),
        ];
    }
}
