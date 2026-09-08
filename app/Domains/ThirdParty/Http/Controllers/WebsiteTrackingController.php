<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Http\Controllers;

use App\Domains\ThirdParty\Models\WebsiteTracker;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class WebsiteTrackingController extends Controller
{
    public function index(): View
    {
        return view('integration.website-tracking', [
            'trackers' => WebsiteTracker::query()->latest('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'domain' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $tracker = WebsiteTracker::query()->create([
            'name' => $validated['name'],
            'domain' => $validated['domain'],
            'status' => $validated['status'] ?? 'active',
        ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'tracker' => $tracker], 201);
        }

        return back()->with('status', 'Website tracker created.');
    }

    public function track(string $token): Response
    {
        $tracker = WebsiteTracker::query()
            ->where('tracking_token', $token)
            ->where('status', 'active')
            ->first();

        if ($tracker === null) {
            abort(404);
        }

        $script = <<<JS
(function(){/* WapApp tracker stub for {$tracker->domain} */})();
JS;

        return response($script, 200, [
            'Content-Type' => 'application/javascript',
        ]);
    }
}
