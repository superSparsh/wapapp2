<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Services\QueueAdminService;
use App\Domains\Admin\Support\AdminListQuery;
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
        $parsed = AdminListQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'queue', 'available_at', 'failed_at'],
            defaultSort: 'id',
            defaultDirection: 'desc',
        );

        $module = $request->query('module');
        $module = is_string($module) ? $module : null;
        $queue = $request->query('queue');
        $queue = is_string($queue) ? $queue : '';

        return view('admin.queues.index', $this->queues->dashboard(
            max(1, (int) $request->integer('page', 1)),
            max(1, (int) $request->integer('failed_page', 1)),
            25,
            [
                'module' => $module,
                'q' => $parsed['q'],
                'queue' => $queue,
                'date_from' => $parsed['date_from'],
                'date_to' => $parsed['date_to'],
                'sort' => $parsed['sort'],
                'direction' => $parsed['direction'],
            ],
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
