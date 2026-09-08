<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CustomerReadinessSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerReadinessController extends Controller
{
    /** @var list<string> */
    public const DOC_TYPES = [
        'gst_certificate',
        'business_license',
        'utility_bill',
        'other',
    ];

    public function create(): View
    {
        return view('customer-readiness.create', [
            'docTypes' => self::DOC_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191'],
            'business_name' => ['required', 'string', 'max:191'],
            'business_email' => ['nullable', 'email', 'max:191'],
            'website' => ['nullable', 'string', 'max:512'],
            'doc_type' => ['required', 'string', 'in:'.implode(',', self::DOC_TYPES)],
        ]);

        CustomerReadinessSubmission::query()->create([
            'customer_name' => $validated['name'],
            'customer_email' => strtolower($validated['email']),
            'business_name' => $validated['business_name'],
            'business_email' => isset($validated['business_email']) ? strtolower((string) $validated['business_email']) : null,
            'website' => $validated['website'] ?? null,
            'doc_type' => $validated['doc_type'],
            'status' => 'pending',
            'data' => $validated,
            'meta' => [
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ],
        ]);

        return redirect()->route('customer-readiness.thanks');
    }

    public function thanks(): View
    {
        return view('customer-readiness.thanks');
    }
}
