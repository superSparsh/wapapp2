<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Services\DpdpReadinessService;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DpdpReadinessController extends Controller
{
    public function __invoke(DpdpReadinessService $readiness): View
    {
        $checklist = $readiness->platformChecklist();

        return view('admin.legal.dpdp-readiness', [
            'summary' => $checklist['summary'],
            'items' => $checklist['items'],
            'config' => $checklist['config'],
        ]);
    }
}
