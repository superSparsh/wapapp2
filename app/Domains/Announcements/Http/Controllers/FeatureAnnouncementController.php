<?php

declare(strict_types=1);

namespace App\Domains\Announcements\Http\Controllers;

use App\Domains\Announcements\Services\FeatureRequestService;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeatureAnnouncementController extends Controller
{
    public function __construct(
        private readonly FeatureRequestService $featureRequests,
    ) {}

    public function index(): View
    {
        return view('announcements.features', [
            'announcements' => $this->featureRequests->listActiveForTenant(),
        ]);
    }

    public function requestActivation(Request $request, Announcement $announcement): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $tenantId = (string) (tenant('id') ?? '');
        abort_if($tenantId === '', 403);

        $result = $this->featureRequests->requestActivation($announcement, $user, $tenantId);

        $flashKey = ($result['status'] ?? 200) >= 400 ? 'error' : 'status';

        return back()->with($flashKey, $result['message']);
    }
}
