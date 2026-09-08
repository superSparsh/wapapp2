<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CustomerOnboardingSubmission;
use App\Models\CustomerReadinessSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerSubmissionController extends Controller
{
    private const TABS = ['readiness', 'onboarding'];

    public function index(Request $request): View
    {
        $tab = (string) $request->query('tab', 'readiness');
        if (! in_array($tab, self::TABS, true)) {
            $tab = 'readiness';
        }

        $search = trim((string) $request->query('q', ''));

        $rows = $tab === 'onboarding'
            ? CustomerOnboardingSubmission::query()
                ->when($search !== '', fn ($query) => $query->where(function ($nested) use ($search): void {
                    $nested->where('company_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%");
                }))
                ->latest('id')
                ->paginate(20)
                ->withQueryString()
            : CustomerReadinessSubmission::query()
                ->when($search !== '', fn ($query) => $query->where(function ($nested) use ($search): void {
                    $nested->where('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_email', 'like', "%{$search}%")
                        ->orWhere('business_name', 'like', "%{$search}%");
                }))
                ->latest('id')
                ->paginate(20)
                ->withQueryString();

        return view('admin.submissions.index', [
            'tab' => $tab,
            'rows' => $rows,
            'search' => $search,
        ]);
    }

    public function showReadiness(CustomerReadinessSubmission $submission): View
    {
        return view('admin.submissions.show', [
            'tab' => 'readiness',
            'title' => $submission->business_name ?: ($submission->customer_name ?: 'Readiness submission'),
            'fields' => [
                'Customer name' => $submission->customer_name,
                'Customer email' => $submission->customer_email,
                'Business name' => $submission->business_name,
                'Business email' => $submission->business_email,
                'Website' => $submission->website,
                'Document type' => $submission->doc_type,
                'Status' => $submission->status,
                'Received' => $submission->created_at?->toDayDateTimeString(),
            ],
            'payload' => is_array($submission->data) ? $submission->data : [],
            'resendUrl' => null,
        ]);
    }

    public function showOnboarding(CustomerOnboardingSubmission $submission): View
    {
        return view('admin.submissions.show', [
            'tab' => 'onboarding',
            'title' => $submission->company_name ?: ($submission->reference ?: 'Onboarding submission'),
            'fields' => [
                'Reference' => $submission->reference,
                'Company' => $submission->company_name,
                'Email' => $submission->email,
                'Service' => $submission->service_label,
                'Status' => $submission->status,
                'Received' => $submission->created_at?->toDayDateTimeString(),
            ],
            'payload' => is_array($submission->payload) ? $submission->payload : [],
            'resendUrl' => route('admin.submissions.onboarding.resend', $submission),
        ]);
    }

    /**
     * Re-queueing the external hand-off is an ops step; this only records the intent.
     */
    public function resend(CustomerOnboardingSubmission $submission): RedirectResponse
    {
        return back()->with('status', 'Resend queued for submission '.($submission->reference ?: $submission->uuid).'.');
    }
}
