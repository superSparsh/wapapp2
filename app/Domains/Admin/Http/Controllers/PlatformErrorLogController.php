<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Services\PlatformErrorLogService;
use App\Domains\Admin\Support\AdminListQuery;
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

        $parsed = AdminListQuery::fromRequest(
            $request,
            allowedSorts: ['occurred_at', 'type', 'source', 'tenant_id', 'id'],
            defaultSort: 'occurred_at',
            defaultDirection: 'desc',
        );

        $type = (string) $request->query('type', 'all');
        if (! in_array($type, ['all', 'exception', 'api', 'job'], true)) {
            $type = 'all';
        }

        $tenantId = $request->query('tenant_id');
        $tenantId = is_string($tenantId) && $tenantId !== '' ? $tenantId : null;

        $filters = [
            'type' => $type,
            'tenant_id' => $tenantId,
            'q' => $parsed['q'],
            'date_from' => $parsed['date_from'],
            'date_to' => $parsed['date_to'],
            'sort' => $parsed['sort'],
            'direction' => $parsed['direction'],
        ];

        return view('admin.errors.show', [
            'module' => $module,
            'moduleLabel' => $this->resolver->label($module),
            'type' => $type,
            'tenantId' => $tenantId,
            'filters' => $filters,
            'logs' => $this->errors->forModule($module, $filters),
            'sortOptions' => [
                ['value' => 'occurred_at', 'label' => 'Newest first', 'direction' => 'desc'],
                ['value' => 'occurred_at', 'label' => 'Oldest first', 'direction' => 'asc'],
                ['value' => 'type', 'label' => 'Type A–Z', 'direction' => 'asc'],
                ['value' => 'source', 'label' => 'Source A–Z', 'direction' => 'asc'],
                ['value' => 'tenant_id', 'label' => 'Tenant', 'direction' => 'asc'],
            ],
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
