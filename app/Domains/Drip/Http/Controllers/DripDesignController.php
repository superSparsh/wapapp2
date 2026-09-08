<?php

declare(strict_types=1);

namespace App\Domains\Drip\Http\Controllers;

use App\Domains\Drip\Http\Requests\UpdateDripCampaignRequest;
use App\Domains\Drip\Services\DripCampaignService;
use App\Domains\Drip\Support\DripNodeCatalog;
use App\Http\Controllers\Controller;
use App\Models\DripCampaign;
use App\Models\MailList;
use App\Support\PublicId;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DripDesignController extends Controller
{
    public function __construct(
        private readonly DripCampaignService $campaignService,
    ) {}

    /**
     * Show the design/settings page for a drip campaign.
     */
    public function edit(DripCampaign $campaign): View
    {
        $campaign->load('audience');

        $audiences = MailList::query()
            ->select('id', 'uuid', 'name')
            ->orderBy('name')
            ->get();

        $timezones = \DateTimeZone::listIdentifiers();

        return view('automation.drip-design', [
            'campaign' => $campaign,
            'audiences' => $audiences,
            'timezones' => $timezones,
            'timezoneOptions' => $this->timezoneOptions(),
            'flowData' => $campaign->exported_data ?? ['nodes' => [], 'edges' => []],
            'categories' => DripNodeCatalog::categoriesForController(),
            'nodeTypes' => DripNodeCatalog::nodeTypesForController(),
            'nodeOptions' => DripNodeCatalog::flatOptions(),
        ]);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function timezoneOptions(): array
    {
        return collect(\DateTimeZone::listIdentifiers())
            ->map(function (string $timezone): array {
                try {
                    $offset = (new \DateTimeImmutable('now', new \DateTimeZone($timezone)))->format('P');
                    $label = '(GMT'.$offset.') '.$timezone;
                } catch (\Exception) {
                    $label = $timezone;
                }

                return ['value' => $timezone, 'label' => $label];
            })
            ->all();
    }

    /**
     * Update campaign settings.
     */
    public function update(UpdateDripCampaignRequest $request, DripCampaign $campaign): RedirectResponse
    {
        $data = $request->validated();
        if (array_key_exists('audience_id', $data)) {
            $list = PublicId::find(MailList::class, $data['audience_id'] ?? null);
            $data['audience_id'] = $list?->id;
        }

        $this->campaignService->updateSettings($campaign, $data);

        return redirect()
            ->route('automation.drip.design', $campaign)
            ->with('status', 'Campaign settings updated successfully.');
    }
}
