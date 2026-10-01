<?php

declare(strict_types=1);

namespace App\Domains\MobileApi\Http\Controllers;

use App\Domains\Inbox\Services\InboxQueryService;
use App\Domains\MobileApi\Services\MobileLineResolver;
use App\Domains\Templates\Enums\TemplateStatus;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Template;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        private readonly InboxQueryService $queryService,
        private readonly MobileLineResolver $lines,
    ) {}

    public function dashboard(): JsonResponse
    {
        $unread = $this->queryService->unreadSnapshot();
        $line = null;
        try {
            $line = $this->lines->resolve();
        } catch (\Throwable) {
            //
        }

        return response()->json([
            'success' => true,
            'data' => [
                'unread_messages' => (int) ($unread['total'] ?? $unread['unread_total'] ?? 0),
                'unread_snapshot' => $unread,
                'templates_approved' => Template::query()->where('status', TemplateStatus::Approved)->count(),
                'campaigns_recent' => Campaign::query()->orderByDesc('id')->limit(5)->get(['id', 'uuid', 'name', 'status', 'created_at']),
                'default_whatsapp_number' => $line?->phone,
                'tenant_id' => tenant('id'),
            ],
        ]);
    }
}
