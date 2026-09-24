<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Services\DataPurgeService;
use App\Domains\Admin\Support\AdminListQuery;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DataPurgeController extends Controller
{
    public function __construct(
        private readonly DataPurgeService $purge,
    ) {}

    public function index(Request $request): View
    {
        $parsed = AdminListQuery::fromRequest(
            $request,
            allowedSorts: ['name', 'valid_until', 'status', 'reason'],
            defaultSort: 'name',
            defaultDirection: 'asc',
        );

        return view('admin.data-purge.index', [
            ...$this->purge->candidates(
                array_merge($request->only(['q']), [
                    'sort' => $parsed['sort'],
                    'direction' => $parsed['direction'],
                ]),
                (int) $request->integer('page', 1),
            ),
            'sortOptions' => [
                ['value' => 'name', 'label' => 'Name A–Z', 'direction' => 'asc'],
                ['value' => 'valid_until', 'label' => 'Valid until', 'direction' => 'asc'],
                ['value' => 'status', 'label' => 'Status', 'direction' => 'asc'],
                ['value' => 'reason', 'label' => 'Reason', 'direction' => 'asc'],
            ],
        ]);
    }

    public function show(Tenant $tenant): View
    {
        return view('admin.data-purge.show', $this->purge->preview($tenant));
    }

    public function mark(Tenant $tenant): RedirectResponse
    {
        $this->purge->markForPurge($tenant, (string) (auth('admin')->user()?->name ?? 'Admin'));

        return back()->with('status', 'Customer marked for purge and suspended.');
    }

    public function unmark(Tenant $tenant): RedirectResponse
    {
        $this->purge->clearPurgeMark($tenant);

        return back()->with('status', 'Purge mark cleared.');
    }
}
