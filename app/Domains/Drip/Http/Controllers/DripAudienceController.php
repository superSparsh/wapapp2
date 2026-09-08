<?php

declare(strict_types=1);

namespace App\Domains\Drip\Http\Controllers;

use App\Domains\Drip\Http\Requests\TriggerDripAudienceRequest;
use App\Domains\Drip\Services\DripAudiencePresenter;
use App\Domains\Drip\Services\DripTriggerDispatcher;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\DripCampaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DripAudienceController extends Controller
{
    public function __construct(
        private readonly DripAudiencePresenter $audiencePresenter,
        private readonly DripTriggerDispatcher $triggerDispatcher,
    ) {}

    /**
     * Audience contacts list with stats grid.
     */
    public function contacts(DripCampaign $campaign, \Illuminate\Http\Request $request): View
    {
        $result = $this->audiencePresenter->contacts(
            $campaign,
            perPage: 10,
            search: $request->query('search'),
        );

        return view('automation.drip-audience', [
            'campaign' => $campaign,
            'contacts' => $result['contacts'],
            'totalContacts' => $result['total'],
            'statsGrid' => $this->audiencePresenter->statsGrid($campaign),
        ]);
    }

    /**
     * Timeline activity feed.
     */
    public function timeline(DripCampaign $campaign): View
    {
        return view('automation.drip-audience-empty', [
            'campaign' => $campaign,
            'activities' => $this->audiencePresenter->timeline($campaign),
        ]);
    }

    /**
     * Manually trigger automation for a specific contact.
     */
    public function trigger(DripCampaign $campaign, TriggerDripAudienceRequest $request): RedirectResponse
    {
        $phone = (string) $request->validated('phone');
        $contact = Contact::query()->where('phone', $phone)->first();

        if ($contact !== null) {
            $this->triggerDispatcher->dispatchForContact((string) $campaign->trigger_type, $contact);
        }

        return redirect()
            ->route('automation.drip.audience', $campaign)
            ->with('status', "Trigger initiated for {$phone}.");
    }
}
