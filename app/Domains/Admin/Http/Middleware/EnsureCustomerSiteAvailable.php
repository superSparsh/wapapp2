<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Middleware;

use App\Domains\Admin\Services\MaintenanceModeService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerSiteAvailable
{
    public function __construct(
        private readonly MaintenanceModeService $maintenance,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->maintenance->enabled()) {
            return $next($request);
        }

        // Admin panel + health always reachable.
        if ($request->is('admin', 'admin/*', 'up', 'horizon', 'horizon/*')) {
            return $next($request);
        }

        // Inbound WhatsApp / flow / shopify webhooks stay reachable when module on
        // (these routes are usually outside `web`, but guard anyway).
        if ($request->is(
            'api/v1/message-uplink/*',
            'api/v1/status-uplink/*',
            'v1/message-uplink/*',
            'v1/status-uplink/*',
            'v1/flow-exchange/*',
            'api/v1/webhooks/*',
            'webhooks/*',
        )) {
            return $next($request);
        }

        // Customer API — optional keep-alive.
        if ($request->is('api/*', 'v1/*') && $this->maintenance->moduleEnabled('customer_api')) {
            return $next($request);
        }

        if ($request->is('api/*', 'v1/*') && ! $this->maintenance->moduleEnabled('customer_api')) {
            return response()->json([
                'message' => $this->maintenance->message(),
                'maintenance' => true,
            ], 503);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => $this->maintenance->message(),
                'maintenance' => true,
            ], 503);
        }

        return response()
            ->view('maintenance', [
                'message' => $this->maintenance->message(),
                'until' => $this->maintenance->until(),
                'appName' => config('app.name', 'WapApp'),
            ], 503)
            ->header('Retry-After', '3600');
    }
}
