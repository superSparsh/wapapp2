<?php

declare(strict_types=1);

namespace App\Domains\Audience\Http\Controllers;

use App\Domains\Audience\Http\Requests\MailList\StoreMailListRequest;
use App\Domains\Audience\Http\Requests\MailList\UpdateMailListRequest;
use App\Domains\Audience\Services\MailListService;
use App\Models\MailList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class MailListController extends Controller
{
    public function __construct(
        private readonly MailListService $service,
    ) {}

    /**
     * My Lists — index page.
     */
    public function index(Request $request): View
    {
        $lists = $this->service->index($request->get('search'));

        return view('audience.index', ['lists' => $lists]);
    }

    /**
     * Overview page for a specific list or global.
     */
    public function overview(Request $request): View
    {
        $mailList = $request->has('list')
            ? MailList::query()->findOrFail($request->integer('list'))
            : null;

        $stats = $this->service->overview($mailList);
        $growth = $this->service->growthChart($mailList);
        $subscriberSeries = [];
        foreach ($growth['columns'] as $index => $label) {
            $subscriberSeries[] = [
                'label' => $label,
                'value' => (int) ($growth['total'][$index] ?? 0),
            ];
        }

        return view('audience.overview', [
            'mailList' => $mailList,
            'stats' => $stats,
            'subscriberSeries' => array_slice($subscriberSeries, -6),
        ]);
    }

    /**
     * Store a new mail list.
     */
    public function store(StoreMailListRequest $request): RedirectResponse
    {
        $this->service->store($request->validated());

        return redirect()->route('audience.index')
            ->with('status', 'List created successfully.');
    }

    /**
     * Update a mail list.
     */
    public function update(UpdateMailListRequest $request, MailList $mailList): RedirectResponse
    {
        $this->service->update($mailList, $request->validated());

        return redirect()->route('audience.index')
            ->with('status', 'List updated successfully.');
    }

    /**
     * Delete a mail list.
     */
    public function destroy(MailList $mailList): RedirectResponse
    {
        $this->service->destroy($mailList);

        return redirect()->route('audience.index')
            ->with('status', 'List deleted successfully.');
    }

    /**
     * Growth chart JSON for a list.
     */
    public function growthChart(MailList $mailList): JsonResponse
    {
        return response()->json($this->service->growthChart($mailList));
    }

    /**
     * Statistics chart JSON for a list.
     */
    public function statisticsChart(MailList $mailList): JsonResponse
    {
        return response()->json(['data' => $this->service->statisticsChart($mailList)]);
    }

    /**
     * Settings page for a list.
     */
    public function settings(Request $request): View
    {
        $mailList = $request->has('list')
            ? MailList::query()->findOrFail($request->integer('list'))
            : null;

        return view('audience.settings', ['mailList' => $mailList]);
    }
}
