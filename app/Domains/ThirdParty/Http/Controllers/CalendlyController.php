<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Http\Controllers;

use App\Domains\ThirdParty\Http\Requests\Calendly\UpdateCalendlyRequest;
use App\Domains\ThirdParty\Models\CalendlyWebhookLog;
use App\Domains\ThirdParty\Services\CalendlyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class CalendlyController extends Controller
{
    public function __construct(
        private readonly CalendlyService $calendly,
    ) {}

    public function index(Request $request): View
    {
        $userId      = (int) auth()->id();
        $integration = $this->calendly->findOrCreate($userId);

        $connected = $integration->isEnabled() && $integration->hasAccessToken();

        // Auto-detect active tab from query params
        $activeTab = $request->get('tab');
        if ($activeTab === null && ($request->has('page') || $request->has('status'))) {
            $activeTab = 'events';
        }
        if ($activeTab === null && ($request->has('logs_page') || $request->has('log_type'))) {
            $activeTab = 'logs';
        }
        $activeTab ??= 'token';

        $status   = $request->get('status');
        $logType  = $request->get('log_type');
        $events      = $this->calendly->events($userId, $status, 10);
        $messageLogs = $this->calendly->messageLogs($userId, $logType, 15);

        return view('integration.calendly', compact(
            'integration',
            'connected',
            'events',
            'messageLogs',
            'activeTab',
        ));
    }

    public function toggle(): RedirectResponse
    {
        $userId      = (int) auth()->id();
        $integration = $this->calendly->findOrCreate($userId);
        $this->calendly->toggle($integration);

        return redirect()->route('integration.calendly')
            ->with('status', 'Plugin status updated.');
    }

    public function update(UpdateCalendlyRequest $request): RedirectResponse
    {
        $userId      = (int) auth()->id();
        $integration = $this->calendly->findOrCreate($userId);
        $validated   = $request->validated();
        $input       = $validated['settings'];

        $oldSettings  = $integration->settings ?? [];
        $oldToken     = $oldSettings['access_token'] ?? null;
        $newToken     = $input['access_token'];
        $tokenChanged = $oldToken !== $newToken;

        // Duplicate token check
        if ($tokenChanged) {
            $exists = \App\Domains\ThirdParty\Models\CalendlyIntegration::query()
                ->where('user_id', '!=', $userId)
                ->whereJsonContains('settings->access_token', $newToken)
                ->exists();

            if ($exists) {
                return back()->withErrors(['settings.access_token' => 'Access token already in use by another user.']);
            }
        }

        $newSettings = array_merge($oldSettings, [
            'access_token'    => $newToken,
            'user_email'      => $input['user_email'] ?? $oldSettings['user_email'] ?? null,
            'whatsapp_number' => $input['whatsapp_number'] ?? $oldSettings['whatsapp_number'] ?? null,
            'enable_whatsapp' => isset($input['enable_whatsapp']),
        ]);

        $isValid = ! $tokenChanged || $this->calendly->validateToken($newToken);

        if ($isValid && ! $integration->isEnabled()) {
            $integration->status = \App\Domains\ThirdParty\Enums\IntegrationStatus::Enabled;
        }

        $this->calendly->saveSettings($integration, $newSettings, $tokenChanged);

        if ($tokenChanged) {
            return redirect()->route('integration.calendly')->with(
                $isValid
                    ? ['success' => 'Calendly access token saved and connected successfully.']
                    : ['error'   => 'Invalid Access Token. Please check and try again.']
            );
        }

        return redirect()->route('integration.calendly')
            ->with('success', 'Notification settings updated successfully.');
    }

    public function test(Request $request): JsonResponse
    {
        $token = (string) $request->input('access_token', '');
        if (empty($token)) {
            return response()->json(['success' => false, 'message' => 'No token provided.'], 422);
        }

        $ok = $this->calendly->validateToken($token);

        return response()->json([
            'success' => $ok,
            'message' => $ok ? 'Token verified.' : 'Invalid token or unauthorized.',
        ], $ok ? 200 : 422);
    }

    public function syncEvents(): JsonResponse
    {
        $userId      = (int) auth()->id();
        $integration = $this->calendly->findOrCreate($userId);

        if (! $integration->hasAccessToken()) {
            return response()->json(['success' => false, 'message' => 'Access token not found.']);
        }

        $tenantId = tenancy()->tenant?->getTenantKey();
        $this->calendly->dispatchSync($integration, (string) $tenantId);

        return response()->json([
            'success' => true,
            'message' => 'Sync started in background. Events will update shortly.',
        ]);
    }

    public function receiveWebhook(Request $request): \Illuminate\Http\Response
    {
        CalendlyWebhookLog::query()->create([
            'payload'   => $request->all(),
            'processed' => false,
        ]);

        return response('', 200);
    }
}
