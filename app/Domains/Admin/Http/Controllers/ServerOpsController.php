<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Services\ServerOpsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class ServerOpsController extends Controller
{
    public function __construct(
        private readonly ServerOpsService $ops,
    ) {}

    public function index(): View
    {
        $probes = $this->ops->probes();

        return view('admin.server-ops.index', [
            'ops_groups' => $this->ops->catalogByGroup(),
            'horizon' => $probes['horizon'],
            'redis_probe' => $probes['redis'],
        ]);
    }

    public function run(Request $request): RedirectResponse
    {
        $key = (string) $request->input('command', '');
        abort_if($key === '', 422, 'Command is required.');

        try {
            $result = $this->ops->run($key);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $flash = $result['label'].': '.$result['output'];

        return back()
            ->with($result['ok'] ? 'status' : 'error', $flash)
            ->with('ops_output', $result['output'])
            ->with('ops_command', $result['key']);
    }
}
