<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Support\AdminListQuery;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
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

        $query = Announcement::query();
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
