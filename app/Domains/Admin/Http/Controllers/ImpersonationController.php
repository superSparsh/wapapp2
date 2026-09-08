<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Services\AdminImpersonationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class ImpersonationController extends Controller
{
    public function stop(AdminImpersonationService $impersonation): RedirectResponse
    {
        try {
            $impersonation->stop();
        } catch (RuntimeException $e) {
            return redirect()->route('admin.login')->withErrors(['email' => $e->getMessage()]);
        }

        return redirect()->route('admin.dashboard')
            ->with('status', 'Returned to admin panel.');
    }
}
