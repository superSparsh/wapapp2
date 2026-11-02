<?php

declare(strict_types=1);

namespace App\Domains\Account\Http\Controllers;

use App\Domains\Account\Services\BusinessCustomTermsPdfService;
use App\Domains\Account\Services\BusinessCustomTermsService;
use App\Domains\Account\Services\SignedAgreementService;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BusinessTermsController extends Controller
{
    public function show(
        BusinessCustomTermsService $customTerms,
        SignedAgreementService $signedAgreements,
    ): View {
        $tenant = $this->currentTenant();

        return view('profile.business-terms', [
            'term' => $customTerms->forTenant((string) $tenant->id),
            'signed' => $signedAgreements->resolveForTenant($tenant),
            'metaPolicies' => config('legal.meta_policies', []),
            'maxChars' => (int) config('legal.custom_terms.max_chars', 50000),
        ]);
    }

    public function update(Request $request, BusinessCustomTermsService $customTerms): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:'.(int) config('legal.custom_terms.title_max', 191)],
            'body' => ['required', 'string', 'max:'.(int) config('legal.custom_terms.max_chars', 50000)],
            'submit_for_review' => ['nullable', 'boolean'],
        ]);

        $validated['submit_for_review'] = $request->boolean('submit_for_review');

        try {
            $term = $customTerms->saveDraft($this->currentTenant(), $validated);
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('profile.business-terms')
                ->withErrors(['body' => $e->getMessage()]);
        }

        $message = $term->status?->value === 'pending_review'
            ? 'Terms submitted for admin review.'
            : 'Draft saved.';

        return redirect()
            ->route('profile.business-terms')
            ->with('status', $message);
    }

    public function downloadSignedAgreement(SignedAgreementService $signedAgreements): BinaryFileResponse|Response
    {
        $path = $signedAgreements->absolutePdfPathForTenant($this->currentTenant());
        if ($path === null) {
            abort(404, 'Signed agreement PDF not found.');
        }

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
        ]);
    }

    public function downloadCustomTermsPdf(
        BusinessCustomTermsService $customTerms,
        BusinessCustomTermsPdfService $pdf,
    ): StreamedResponse {
        $tenant = $this->currentTenant();
        $term = $customTerms->forTenant((string) $tenant->id);
        abort_unless($term !== null && filled($term->body), 404, 'Custom terms PDF is not available yet.');

        $filename = $pdf->filename($term, $tenant);
        $binary = $pdf->render($term, $tenant);

        return response()->streamDownload(static function () use ($binary): void {
            echo $binary;
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function currentTenant(): Tenant
    {
        $tenant = tenant();
        abort_unless($tenant instanceof Tenant, 404);

        return $tenant;
    }
}
