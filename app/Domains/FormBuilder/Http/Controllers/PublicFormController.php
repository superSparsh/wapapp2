<?php

declare(strict_types=1);

namespace App\Domains\FormBuilder\Http\Controllers;

use App\Domains\FormBuilder\Http\Requests\FormSubmissionRequest;
use App\Domains\FormBuilder\Services\FormSubmissionService;
use App\Http\Controllers\Controller;
use App\Models\SignupForm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PublicFormController extends Controller
{
    public function __construct(
        private readonly FormSubmissionService $submissionService,
    ) {}

    /**
     * Render the public form by slug.
     */
    public function show(string $slug): View
    {
        $form = SignupForm::query()
            ->where('slug', $slug)
            ->where('status', 'active')
            ->firstOrFail();

        return view('form-builder.public', [
            'form' => $form,
            'fields' => $fields,
            'redirectUrl' => $form->redirect_url,
        ]);
    }

    /**
     * Process a public form submission.
     */
    public function submit(FormSubmissionRequest $request, string $slug): RedirectResponse|JsonResponse
    {
        $form = SignupForm::query()
            ->where('slug', $slug)
            ->where('status', 'active')
            ->firstOrFail();

        $submission = $this->submissionService->process($form, $request->validated());

        $redirectUrl = $form->redirect_url;

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Form submitted successfully.',
                'redirect_url' => $redirectUrl,
            ]);
        }

        if ($redirectUrl) {
            return redirect()->away($redirectUrl)->with('status', 'Form submitted successfully.');
        }

        return redirect()->back()->with('status', 'Form submitted successfully.');
    }

    /**
     * Serve the embed JavaScript for external websites.
     */
    public function embedJs(string $slug): Response
    {
        $form = SignupForm::query()
            ->where('slug', $slug)
            ->where('status', 'active')
            ->firstOrFail();

        $embedUrl = $form->publicUrl();

        $js = <<<JS
(function() {
    var container = document.getElementById('wapapp-form-{$form->uuid}');
    if (!container) return;

    var iframe = document.createElement('iframe');
    iframe.src = '{$embedUrl}';
    iframe.style.width = '100%';
    iframe.style.height = '600px';
    iframe.style.border = 'none';
    iframe.style.overflow = 'hidden';
    container.appendChild(iframe);
})();
JS;

        return response($js, 200, [
            'Content-Type' => 'application/javascript',
            'Cache-Control' => 'public, max-age=' . config('form-builder.public_cache_seconds', 600),
        ]);
    }
}
