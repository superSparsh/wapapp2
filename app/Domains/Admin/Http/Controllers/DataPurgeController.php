<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Services\DataPurgeService;
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
        return view('admin.data-purge.index', $this->purge->candidates(
            $request->only(['q']),
            (int) $request->integer('page', 1),
        ));
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
