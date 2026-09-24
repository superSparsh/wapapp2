<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Support\AdminListQuery;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementFeatureRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $parsed = AdminListQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'title', 'starts_at', 'ends_at'],
            defaultSort: 'id',
            defaultDirection: 'desc',
        );

        $query = Announcement::query()->withCount('featureRequests');
        AdminListQuery::applySearch($query, $parsed['q'], ['title', 'body']);
        AdminListQuery::applySort(
            $query,
            $parsed['sort'],
            $parsed['direction'],
            [
                'id' => 'id',
                'title' => 'title',
                'starts_at' => 'starts_at',
                'ends_at' => 'ends_at',
            ],
            'id',
        );

        return view('admin.announcements.index', [
            'announcements' => $query->paginate(20)->withQueryString(),
            'filters' => $parsed,
            'sortOptions' => [
                ['value' => 'id', 'label' => 'Newest first', 'direction' => 'desc'],
                ['value' => 'title', 'label' => 'Title A–Z', 'direction' => 'asc'],
                ['value' => 'starts_at', 'label' => 'Starts', 'direction' => 'desc'],
                ['value' => 'ends_at', 'label' => 'Ends', 'direction' => 'desc'],
            ],
        ]);
    }

    public function requests(Request $request, Announcement $announcement): View
    {
        $parsed = AdminListQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'created_at', 'customer_name', 'is_acknowledged'],
            defaultSort: 'id',
            defaultDirection: 'desc',
        );

        $query = AnnouncementFeatureRequest::query()
            ->where('announcement_id', $announcement->id);

        AdminListQuery::applySort($query, $parsed['sort'], $parsed['direction'], [
            'id' => 'id',
            'created_at' => 'created_at',
            'customer_name' => 'customer_name',
            'is_acknowledged' => 'is_acknowledged',
        ], 'id');

        return view('admin.announcements.requests', [
            'announcement' => $announcement,
            'requests' => $query->paginate(25)->withQueryString(),
            'filters' => $parsed,
            'sortOptions' => [
                ['value' => 'id', 'label' => 'Newest first', 'direction' => 'desc'],
                ['value' => 'created_at', 'label' => 'Requested date', 'direction' => 'desc'],
                ['value' => 'customer_name', 'label' => 'Customer A–Z', 'direction' => 'asc'],
                ['value' => 'is_acknowledged', 'label' => 'Acknowledged first', 'direction' => 'desc'],
            ],
        ]);
    }

    public function acknowledgeRequest(AnnouncementFeatureRequest $featureRequest): RedirectResponse
    {
        $featureRequest->update([
            'is_acknowledged' => true,
            'is_viewed' => true,
        ]);

        return back()->with('status', 'Request acknowledged.');
    }

    public function destroyRequest(AnnouncementFeatureRequest $featureRequest): RedirectResponse
    {
        $featureRequest->delete();

        return back()->with('status', 'Request deleted.');
    }

    public function create(): View
    {
        return view('admin.announcements.form');
    }

    public function store(Request $request): RedirectResponse
    {
        Announcement::query()->create($this->validated($request));

        return redirect()->route('admin.announcements.index')->with('status', 'Announcement created.');
    }

    public function edit(Announcement $announcement): View
    {
        return view('admin.announcements.form', compact('announcement'));
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $announcement->update($this->validated($request));

        return redirect()->route('admin.announcements.index')->with('status', 'Announcement updated.');
    }

    public function toggle(Announcement $announcement): RedirectResponse
    {
        $announcement->is_active = ! $announcement->is_active;
        $announcement->save();

        return back()->with('status', 'Announcement '.($announcement->is_active ? 'enabled' : 'disabled').'.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return back()->with('status', 'Announcement deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'body' => ['required', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
