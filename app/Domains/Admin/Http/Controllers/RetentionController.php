<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Services\RetentionService;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RetentionController extends Controller
{
    public function __construct(
        private readonly RetentionService $retention,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'window', 'status']);
        $report = $this->retention->report($filters, (int) $request->integer('page', 1));

        return view('admin.retention.index', $report);
    }

    public function show(Tenant $tenant): View
    {
        return view('admin.retention.show', $this->retention->detail($tenant));
    }

    public function storeNote(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
            'action_type' => ['nullable', 'string', 'max:50'],
        ]);

        $this->retention->addNote(
            $tenant,
            $validated['note'],
            (string) ($validated['action_type'] ?? 'retention_note'),
            (string) (auth('admin')->user()?->name ?? 'Admin'),
        );

        return back()->with('status', 'Retention note saved.');
    }

    public function export(Request $request): StreamedResponse
    {
        return $this->retention->exportCsv($request->only(['q', 'window', 'status']));
    }
}
