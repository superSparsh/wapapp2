<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Services\CustomerReadinessService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Public customer readiness endpoints (legacy route parity).
 * Form blades are imported separately — this controller is the backend only.
 */
class CustomerReadinessController extends Controller
{
    public function __construct(
        private readonly CustomerReadinessService $readiness,
    ) {}

    public function create(): View
    {
        return view('customer-readiness.create', [
            'docTypes' => config('customer-readiness.doc_types', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $result = $this->readiness->process($request);

        return redirect()
            ->route('customer-readiness.result')
            ->with('readiness_result', $result['result']);
    }

    public function storeApi(Request $request): JsonResponse
    {
        try {
            $result = $this->readiness->process($request);

            return response()->json([
                'success' => true,
                'id' => $result['submission']->id,
                'uuid' => $result['submission']->uuid,
                'eligible' => $result['eligible'],
                'meta' => $result['submission']->meta,
                'result' => $result['result'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Server error',
            ], 500);
        }
    }

    public function validateFacebookPage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page_name' => ['required', 'string', 'min:2', 'max:255'],
        ]);

        $result = $this->readiness->validateFacebookPage((string) $validated['page_name']);

        return response()->json($result, ($result['valid'] ?? false) ? 200 : 422);
    }

    public function validateBusinessEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'business_email' => ['required', 'email'],
        ]);

        $result = $this->readiness->validateBusinessEmail((string) $validated['business_email']);

        return response()->json($result, ($result['valid'] ?? false) ? 200 : 422);
    }

    public function result(): View|RedirectResponse
    {
        if (! session()->has('readiness_result')) {
            return redirect()->route('customer-readiness.create');
        }

        return view('customer-readiness.result', [
            'result' => session('readiness_result'),
        ]);
    }

    public function thanks(): View
    {
        return view('customer-readiness.thanks');
    }
}
