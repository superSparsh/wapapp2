<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Http\Controllers;

use App\Domains\Campaigns\Services\CampaignQueryService;
use App\Enums\CampaignStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignListController extends Controller
{
    public function __construct(
        private readonly CampaignQueryService $queryService,
    ) {}

    public function active(Request $request): View
    {
        $paginator = $this->queryService->paginate(
            perPage: (int) config('campaigns.per_page', 10),
            search: $request->query('search'),
            status: CampaignStatus::Sending->value,
            sort: $request->query('sort', 'created_at'),
            direction: $request->query('direction', 'desc'),
        );

        return view('campaigns.active', [
            'campaigns' => $paginator->getCollection(),
            'paginator' => $paginator,
            'search' => $request->query('search', ''),
            'currentSort' => $request->query('sort', 'created_at'),
            'currentDirection' => $request->query('direction', 'desc'),
        ]);
    }

    public function scheduled(Request $request): View
    {
        $paginator = $this->queryService->paginate(
            perPage: (int) config('campaigns.per_page', 10),
            search: $request->query('search'),
            status: CampaignStatus::Scheduled->value,
            sort: $request->query('sort', 'scheduled_at'),
            direction: $request->query('direction', 'desc'),
        );

        return view('campaigns.scheduled', [
            'campaigns' => $paginator->getCollection(),
            'paginator' => $paginator,
            'search' => $request->query('search', ''),
            'currentSort' => $request->query('sort', 'scheduled_at'),
            'currentDirection' => $request->query('direction', 'desc'),
        ]);
    }
}
