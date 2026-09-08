<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Http\Controllers;

use App\Domains\Campaigns\Http\Requests\StoreCampaignRequest;
use App\Domains\Campaigns\Services\CampaignPresenter;
use App\Domains\Campaigns\Services\CampaignServiceAdapter;
use App\Enums\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function __construct(
        private readonly CampaignServiceAdapter $adapter,
        private readonly CampaignPresenter $presenter,
    ) {}

    /**
     * List all campaigns with search, sort, and pagination.
     */
    public function index(Request $request): View
    {
        $paginator = $this->adapter->paginate(
            perPage: (int) config('campaigns.per_page', 10),
            search: $request->query('search'),
            status: $request->query('status'),
            sort: $request->query('sort', 'created_at'),
            direction: $request->query('direction', 'desc'),
        );

        return view('campaigns.index', [
            'campaigns' => $paginator->getCollection(),
            'paginator' => $paginator,
            'search' => $request->query('search', ''),
            'currentStatus' => $request->query('status', ''),
            'currentSort' => $request->query('sort', 'created_at'),
            'currentDirection' => $request->query('direction', 'desc'),
        ]);
    }

    /**
     * Show a single campaign overview.
     */
    public function show(Campaign $bulkCampaign): View
    {
        $bulkCampaign->load('audience', 'whatsappLine', 'template');
        $metrics = $this->adapter->getGaugeMetrics($bulkCampaign);

        return view('campaigns.show', [
            'campaign' => $bulkCampaign,
            'card' => $this->presenter->indexCard($bulkCampaign),
            'metrics' => $metrics,
        ]);
    }

    /**
     * Store a new campaign from wizard data.
     */
    public function store(StoreCampaignRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $sendNow = ($data['send_mode'] ?? 'now') === 'now';

        if ($sendNow) {
            $data['scheduled_at'] = null;
        }

        unset($data['send_mode']);

        $wizard = $request->session()->get('campaign_wizard', []);
        $draftId = (int) ($wizard['draft_id'] ?? 0);
        $draft = $draftId > 0
            ? Campaign::query()
                ->whereIn('status', [CampaignStatus::Draft, CampaignStatus::Scheduled])
                ->find($draftId)
            : null;

        $campaign = $draft instanceof Campaign
            ? $this->adapter->update($draft, $data)
            : $this->adapter->create($data);
        $campaign = $campaign->fresh(['whatsappLine', 'template', 'audience']);

        if (! $sendNow && $campaign->scheduled_at && $campaign->status === CampaignStatus::Draft) {
            $campaign->update(['status' => CampaignStatus::Scheduled]);
            $campaign = $campaign->fresh(['whatsappLine', 'template', 'audience']);
        }

        if ($sendNow) {
            if (! $campaign->whatsapp_line_id || ! $campaign->template_id) {
                return redirect()
                    ->route('campaigns.show', $campaign)
                    ->with('error', 'Campaign saved as draft. From Number and template are required to send.');
            }

            if ((int) $campaign->total_recipients === 0) {
                return redirect()
                    ->route('campaigns.show', $campaign)
                    ->with('error', 'Campaign saved, but no subscribed contacts were found in the selected audience.');
            }

            $this->adapter->launch($campaign);
        }

        $request->session()->forget('campaign_wizard');

        return redirect()
            ->route('campaigns.show', $campaign)
            ->with('status', $sendNow
                ? 'Campaign created and queued for sending.'
                : 'Campaign scheduled successfully.');
    }

    /**
     * Toggle pause/resume for a campaign.
     */
    public function toggle(Campaign $bulkCampaign): RedirectResponse|JsonResponse
    {
        $this->adapter->toggle($bulkCampaign);

        $fresh = $bulkCampaign->fresh();
        $sending = $fresh ? $fresh->isSending() : false;

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'active' => $sending,
                'status' => $sending ? 'sending' : 'paused',
            ]);
        }

        return redirect()
            ->route('campaigns.index')
            ->with('status', 'Campaign status updated.');
    }

    /**
     * Duplicate a campaign.
     */
    public function duplicate(Campaign $bulkCampaign): RedirectResponse
    {
        $clone = $this->adapter->duplicate($bulkCampaign);

        return redirect()
            ->route('campaigns.show', $clone)
            ->with('status', 'Campaign duplicated successfully.');
    }

    /**
     * Delete a campaign.
     */
    public function destroy(Campaign $bulkCampaign): RedirectResponse|JsonResponse
    {
        $this->adapter->delete($bulkCampaign);

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()
            ->route('campaigns.index')
            ->with('status', 'Campaign deleted successfully.');
    }
}
