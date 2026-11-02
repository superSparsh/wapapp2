<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Account\Services\DataPrincipalRequestService;
use App\Domains\Admin\Http\Requests\UpdateDataPrincipalRequestRequest;
use App\Enums\DataPrincipalRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\DataPrincipalRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DataPrincipalRequestController extends Controller
{
    public function index(DataPrincipalRequestService $requests): View
    {
        return view('admin.legal.rights-requests', [
            'requests' => $requests->paginateForAdmin(),
            'statuses' => DataPrincipalRequestStatus::cases(),
            'grievanceEmail' => (string) config('legal.grievance.email'),
            'slaDays' => (int) config('legal.rights.sla_days', config('legal.grievance.response_days', 7)),
        ]);
    }

    public function update(
        UpdateDataPrincipalRequestRequest $httpRequest,
        DataPrincipalRequest $dataPrincipalRequest,
        DataPrincipalRequestService $requests,
    ): RedirectResponse {
        /** @var Admin $admin */
        $admin = $httpRequest->user('admin');

        $requests->updateStatus(
            $dataPrincipalRequest,
            DataPrincipalRequestStatus::from($httpRequest->validated('status')),
            $admin,
            $httpRequest->validated('admin_note'),
        );

        return redirect()
            ->route('admin.rights-requests.index')
            ->with('status', 'Request updated.');
    }
}
