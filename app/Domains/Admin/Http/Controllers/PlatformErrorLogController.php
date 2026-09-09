<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Services\PlatformErrorLogService;
use App\Domains\Admin\Support\ErrorModuleResolver;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PlatformErrorLogController extends Controller
{
    public function __construct(
        private readonly PlatformErrorLogService $errors,
        private readonly ErrorModuleResolver $resolver,
    ) {}

    public function index(): View
    {
        return view('admin.errors.index', [
            'modules' => $this->errors->hubCounts(),
        ]);
    }

    public function show(Request $request, string $module): View
    {
        if (! $this->resolver->isValidModule($module)) {
            throw new NotFoundHttpException('Unknown error module.');
        }

        $type = (string) $request->query('type', 'all');
        $tenantId = $request->query('tenant_id');
        $tenantId = is_string($tenantId) && $tenantId !== '' ? $tenantId : null;

        return view('admin.errors.show', [
            'module' => $module,
            'moduleLabel' => $this->resolver->label($module),
            'type' => in_array($type, ['all', 'exception', 'api', 'job'], true) ? $type : 'all',
            'tenantId' => $tenantId,
            'logs' => $this->errors->forModule(
                $module,
                $type === 'all' ? null : $type,
                $tenantId,
            ),
        ]);
    }

    public function clear(Request $request, string $module): RedirectResponse
    {
        if (! $this->resolver->isValidModule($module)) {
            throw new NotFoundHttpException('Unknown error module.');
        }

        $days = max(1, (int) $request->integer('days', 30));
        $deleted = $this->errors->clearOlderThan($module, $days);

        return redirect()
            ->route('admin.errors.show', ['module' => $module])
            ->with('status', "Cleared {$deleted} error(s) older than {$days} days.");
    }
}
