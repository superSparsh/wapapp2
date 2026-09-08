<?php

declare(strict_types=1);

namespace App\Domains\Drip\Http\Controllers;

use App\Domains\Drip\Http\Requests\StoreDripCampaignRequest;
use App\Domains\Drip\Services\DripCampaignQueryService;
use App\Domains\Drip\Services\DripCampaignService;
use App\Http\Controllers\Controller;
use App\Models\DripCampaign;
use App\Models\MailList;
use App\Support\PublicId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DripCampaignController extends Controller
{
    public function __construct(
        private readonly DripCampaignQueryService $queryService,
        private readonly DripCampaignService $campaignService,
    ) {}

    /**
     * List all drip campaigns with search, sort, and pagination.
     */
    public function index(Request $request): View
    {
        $paginator = $this->queryService->paginate(
            perPage: (int) config('chatbot.drip.per_page', 10),
            search: $request->query('search'),
            status: $request->query('status'),
            sort: $request->query('sort', 'created_at'),
            direction: $request->query('direction', 'desc'),
        );

        return view('automation.drip', [
            'campaigns' => $paginator->getCollection(),
            'paginator' => $paginator,
            'currentSort' => $request->query('sort', 'created_at'),
            'currentDirection' => $request->query('direction', 'desc'),
        ]);
    }

    /**
     * Show create form (redirects to design page after creation).
     */
    public function create(): View
    {
        return view('automation.drip-create');
    }

    /**
     * Store a new drip campaign.
     */
    public function store(StoreDripCampaignRequest $request): RedirectResponse
    {
        $data = $request->validated();
        if (array_key_exists('audience_id', $data)) {
            $list = PublicId::find(MailList::class, $data['audience_id'] ?? null);
            $data['audience_id'] = $list?->id;
        }

        $campaign = $this->campaignService->create($data);

        return redirect()
            ->route('automation.drip.design', $campaign)
            ->with('status', 'Drip campaign created successfully.');
    }

    /**
     * Show redirects to design page.
     */
    public function show(DripCampaign $campaign): RedirectResponse
    {
        return redirect()->route('automation.drip.design', $campaign);
    }

    /**
     * Delete a drip campaign.
     */
    public function destroy(DripCampaign $campaign): RedirectResponse|JsonResponse
    {
        $this->campaignService->delete($campaign);

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()
            ->route('automation.drip.index')
            ->with('status', 'Drip campaign deleted successfully.');
    }

    /**
     * Toggle campaign active/inactive.
     */
    public function toggle(DripCampaign $campaign): RedirectResponse|JsonResponse
    {
        $this->campaignService->toggle($campaign);

        $fresh = $campaign->fresh();
        $active = $fresh->isActive();
        $status = $active ? 'activated' : 'deactivated';

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'active' => $active,
                'status' => $status,
            ]);
        }

        return redirect()
            ->route('automation.drip.index')
            ->with('status', "Drip campaign {$status} successfully.");
    }

    /**
     * Duplicate a drip campaign.
     */
    public function duplicate(DripCampaign $campaign): RedirectResponse
    {
        $clone = $this->campaignService->duplicate($campaign);

        return redirect()
            ->route('automation.drip.design', $clone)
            ->with('status', 'Drip campaign duplicated successfully.');
    }
}
