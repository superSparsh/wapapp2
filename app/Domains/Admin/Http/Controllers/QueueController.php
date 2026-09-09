<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Services\QueueAdminService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QueueController extends Controller
{
    public function __construct(
        private readonly QueueAdminService $queues,
    ) {}

    public function index(Request $request): View
    {
        $module = $request->query('module');
        $module = is_string($module) ? $module : null;

        return view('admin.queues.index', $this->queues->dashboard(
            max(1, (int) $request->integer('page', 1)),
            max(1, (int) $request->integer('failed_page', 1)),
            25,
            $module,
        ));
    }

    public function retry(string $uuid): RedirectResponse
    {
        $this->queues->retryFailed($uuid);

        return back()->with('status', 'Job queued for retry.');
    }

    public function retryAll(): RedirectResponse
    {
        $count = $this->queues->retryAllFailed();

        return back()->with('status', "Retried {$count} failed job(s).");
    }

    public function forget(string $uuid): RedirectResponse
    {
        $this->queues->forgetFailed($uuid);

        return back()->with('status', 'Failed job removed.');
    }

    public function flush(): RedirectResponse
    {
        $this->queues->flushFailed();

        return back()->with('status', 'All failed jobs flushed.');
    }
}
