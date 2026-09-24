<?php

declare(strict_types=1);

namespace App\Domains\AutomationEvents\Http\Controllers;

use App\Domains\AutomationEvents\Services\AutomationEventService;
use App\Http\Controllers\Controller;
use App\Models\AutomationEvent;
use App\Support\ListingSort;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AutomationEventController extends Controller
{
    public function __construct(
        private readonly AutomationEventService $service,
    ) {}

    public function index(Request $request): View
    {
        $parsed = ListingSort::fromRequest(
            $request,
            ['id', 'name', 'event_type', 'status', 'scheduled_at', 'created_at'],
            'id',
            'desc',
        );
        $events = $this->service->paginate(
            (int) $request->integer('per_page', 15),
            $parsed['sort'],
            $parsed['direction'],
        );

        return view('automation.events', [
            'events' => $events,
            'currentSort' => $parsed['sort'],
            'currentDirection' => $parsed['direction'],
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'event_type' => ['required', 'string', 'max:64'],
            'payload' => ['nullable', 'array'],
            'scheduled_at' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:32'],
        ]);

        $event = $this->service->store($validated);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'event' => $event], 201);
        }

        return back()->with('status', 'Automation event created.');
    }

    public function destroy(AutomationEvent $automationEvent): RedirectResponse|JsonResponse
    {
        $this->service->destroy($automationEvent);

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('status', 'Automation event deleted.');
    }
}
