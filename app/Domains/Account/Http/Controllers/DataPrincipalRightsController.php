<?php

declare(strict_types=1);

namespace App\Domains\Account\Http\Controllers;

use App\Domains\Account\Http\Requests\StoreDataPrincipalRequestRequest;
use App\Domains\Account\Services\DataPrincipalRequestService;
use App\Enums\DataPrincipalRequestType;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DataPrincipalRightsController extends Controller
{
    public function show(DataPrincipalRequestService $requests): View
    {
        /** @var User $user */
        $user = auth()->user();

        return view('profile.privacy-rights', [
            'requestTypes' => DataPrincipalRequestType::selectable(),
            'requests' => $requests->forEmail((string) $user->email),
            'grievanceEmail' => (string) config('legal.grievance.email'),
            'slaDays' => (int) config('legal.rights.sla_days', config('legal.grievance.response_days', 7)),
            'productName' => (string) config('legal.product_name', config('app.name', 'WapApp')),
        ]);
    }

    public function store(
        StoreDataPrincipalRequestRequest $request,
        DataPrincipalRequestService $requests,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        $requests->submit([
            'requester_name' => (string) $user->name,
            'requester_email' => (string) $user->email,
            'requester_phone' => $request->validated('requester_phone') ?: ($user->phone ?? null),
            'request_type' => $request->validated('request_type'),
            'description' => $request->validated('description'),
            'nominee_name' => $request->validated('nominee_name'),
            'nominee_email' => $request->validated('nominee_email'),
            'nominee_phone' => $request->validated('nominee_phone'),
            'nominee_relation' => $request->validated('nominee_relation'),
            'tenant_id' => tenant('id') ? (string) tenant('id') : null,
        ], $user);

        return redirect()
            ->route('profile.privacy-rights')
            ->with('status', 'Your request was submitted. We will respond within the stated SLA.');
    }
}
