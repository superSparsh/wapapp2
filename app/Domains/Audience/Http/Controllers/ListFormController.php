<?php

declare(strict_types=1);

namespace App\Domains\Audience\Http\Controllers;

use App\Domains\Audience\Services\EmbeddedFormService;
use App\Models\MailList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Audience → Forms / pages = Embedded Form settings (NOT Form Builder).
 */
class ListFormController extends Controller
{
    public function __construct(
        private readonly EmbeddedFormService $embeddedForms,
    ) {}

    public function show(Request $request): View
    {
        $mailList = $this->resolveList($request);
        $options = $mailList ? $this->embeddedForms->optionsFor($mailList) : $this->embeddedForms->defaultOptions();
        $embedCode = $mailList ? $this->embeddedForms->generateEmbedHtml($mailList) : '';
        $previewUrl = $mailList ? $this->embeddedForms->previewUrl($mailList) : null;

        return view('audience.forms', [
            'mailList' => $mailList,
            'settings' => $options,
            'embedCode' => $embedCode,
            'previewUrl' => $previewUrl,
            'subscriberCount' => $mailList?->contacts()->count() ?? 0,
            'listFieldsUrl' => $mailList
                ? route('audience.list-fields', ['list' => $mailList->id])
                : null,
        ]);
    }

    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $mailList = $this->resolveList($request);
        abort_if($mailList === null, 422, 'Select a list first.');

        $request->merge([
            'show_required_only' => $request->boolean('show_required_only'),
            'include_js' => $request->boolean('include_js'),
            'include_css' => $request->boolean('include_css'),
            'show_invisible_fields' => $request->boolean('show_invisible_fields'),
        ]);

        $request->validate([
            'form_title' => ['required', 'string', 'max:191'],
            'redirect_url' => ['nullable', 'string', 'max:500'],
            'custom_css' => ['nullable', 'string', 'max:50000'],
            'show_required_only' => ['sometimes', 'boolean'],
            'include_js' => ['sometimes', 'boolean'],
            'include_css' => ['sometimes', 'boolean'],
            'show_invisible_fields' => ['sometimes', 'boolean'],
        ]);

        try {
            $mailList = $this->embeddedForms->saveOptions($mailList, [
                'form_title' => $request->input('form_title'),
                'redirect_url' => $request->input('redirect_url'),
                'custom_css' => $request->input('custom_css'),
                'show_required_only' => $request->boolean('show_required_only'),
                'include_js' => $request->boolean('include_js'),
                'include_css' => $request->boolean('include_css'),
                'show_invisible_fields' => $request->boolean('show_invisible_fields'),
            ]);
        } catch (\Throwable $e) {
            report($e);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Could not save embedded form settings. Run tenant migrations if this persists.',
                ], 500);
            }

            throw $e;
        }

        $embedCode = $this->embeddedForms->generateEmbedHtml($mailList);
        $previewUrl = $this->embeddedForms->previewUrl($mailList).'?v='.time();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Embedded form settings saved.',
                'embed_code' => $embedCode,
                'preview_url' => $previewUrl,
            ]);
        }

        return redirect()
            ->route('audience.forms', ['list' => $mailList->id])
            ->with('status', 'Embedded form settings saved.');
    }

    private function resolveList(Request $request): ?MailList
    {
        if ($request->filled('list')) {
            return MailList::query()->findOrFail($request->integer('list'));
        }

        return MailList::query()->orderBy('name')->first();
    }
}
