<?php

declare(strict_types=1);

namespace App\Domains\Templates\Http\Controllers;

use App\Domains\Templates\Http\Requests\SaveAuthRequest;
use App\Domains\Templates\Http\Requests\SaveBodyRequest;
use App\Domains\WhatsappFlow\Services\WhatsappFlowInteractiveService;
use App\Domains\Templates\Http\Requests\SaveButtonsRequest;
use App\Domains\Templates\Http\Requests\SaveCarouselRequest;
use App\Domains\Templates\Http\Requests\SaveFooterRequest;
use App\Domains\Templates\Http\Requests\SaveHeaderRequest;
use App\Domains\Templates\Http\Requests\SaveLtoRequest;
use App\Domains\Templates\Http\Requests\SaveSubmitRequest;
use App\Domains\Templates\Services\BuiltinVariableCatalog;
use App\Domains\Templates\Services\TemplateBuilderService;
use App\Domains\Templates\Services\TemplateMediaService;
use App\Domains\Templates\Services\TemplatePreviewService;
use App\Domains\Templates\Services\TemplateVariableQueryService;
use App\Domains\Templates\Support\TemplateCategoryCatalog;
use App\Domains\Templates\Support\TemplateBuilderFlow;
use App\Domains\Templates\Support\TemplateVariableSyntax;
use App\Http\Controllers\Controller;
use App\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TemplateBuilderController extends Controller
{
    public function __construct(
        private readonly TemplateBuilderFlow $builderFlow,
    ) {}
    public function create(TemplateBuilderService $builderService): RedirectResponse
    {
        $template = $builderService->createDraft();

        return redirect()->route('templates.builder.body', $template);
    }

    public function header(Template $template, TemplatePreviewService $previewService): View|RedirectResponse
    {
        return $this->stepView($template, 'header', $previewService);
    }

    public function saveHeader(SaveHeaderRequest $request, Template $template, TemplateBuilderService $builderService): RedirectResponse
    {
        $payload = $template->wizardPayload();
        $mediaPath = $payload['header']['media_path'] ?? null;
        $mediaUrl = $payload['header']['media_url'] ?? null;

        if ($request->hasFile('header_media')) {
            $stored = app(TemplateMediaService::class)->storeHeaderMedia($request->file('header_media'));
            $mediaPath = $stored['path'];
            $mediaUrl = null;
        } elseif ($request->boolean('use_url')) {
            $mediaUrl = (string) $request->input('media_url', '');
            $mediaPath = null;
        }

        $builderService->saveStep($template, 'header', [
            'type' => (string) $request->input('header_type', 'none'),
            'text' => (string) $request->input('header_text', ''),
            'media_path' => $request->input('header_type') === 'none' ? null : $mediaPath,
            'media_url' => $request->input('header_type') === 'none' ? null : $mediaUrl,
            'use_url' => $request->input('header_type') === 'none' ? false : $request->boolean('use_url'),
            'doc_name' => (string) $request->input('doc_name', ''),
        ]);

        return redirect()->route('templates.builder.footer', $template);
    }

    public function uploadHeaderMedia(
        Request $request,
        Template $template,
        TemplateBuilderService $builderService,
        TemplateMediaService $mediaService,
    ): JsonResponse {
        $request->validate([
            'header_media' => ['required', 'file', 'mimes:jpg,jpeg,png,mp4,3gp,pdf,mp3,wav,aac,ogg,m4a', 'max:16384'],
        ]);

        $stored = $mediaService->storeHeaderMedia($request->file('header_media'));
        $mime = $stored['mime'];
        $headerType = match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            str_starts_with($mime, 'application/pdf') => 'document',
            default => 'document',
        };

        $builderService->saveStep($template, 'header', [
            'type' => $headerType,
            'media_path' => $stored['path'],
        ]);

        return response()->json([
            'path' => $stored['path'],
            'url' => $stored['url'],
            'type' => $headerType,
        ]);
    }

    public function body(
        Template $template,
        TemplatePreviewService $previewService,
        TemplateVariableQueryService $variableQueryService,
        BuiltinVariableCatalog $builtinVariableCatalog,
    ): View|RedirectResponse {
        return $this->stepView($template, 'body', $previewService, [
            'variables' => collect($variableQueryService->paginate(perPage: 100)->items()),
            'builtinVariables' => $builtinVariableCatalog->all(),
            'utilityPresets' => config('template-presets.utility', []),
            'canUseCarousel' => $this->builderFlow->canUseCarousel(),
            'aiSuggestUrl' => route('templates.ai.suggest'),
        ]);
    }

    public function saveBody(SaveBodyRequest $request, Template $template, TemplateBuilderService $builderService): RedirectResponse
    {
        $bodyText = TemplateVariableSyntax::normalizeBodyText((string) $request->input('body_text', ''));

        if (! $template->isSetupComplete()) {
            $category = strtoupper((string) $request->input('category'));

            if (TemplateCategoryCatalog::isCarousel($category) && ! $this->builderFlow->canUseCarousel()) {
                return back()->withErrors(['category' => 'Carousel templates require an advance plan. Please upgrade to access this feature.'])->withInput();
            }

            $builderService->saveStep($template, 'meta', [
                'name' => (string) $request->input('name'),
                'category' => $category,
                'language' => (string) $request->input('language'),
                'template_type' => (string) $request->input('template_type', 'regular'),
                'setup_completed' => true,
            ]);
            $template->refresh();
        }

        if ($bodyText === '' && TemplateCategoryCatalog::isAuthentication($template->category)) {
            $bodyText = '$(verificationCode) is your verification code.';
        }

        $builderService->saveStep($template, 'body', [
            'text' => $bodyText,
            'samples' => $request->input('samples', []),
        ]);

        $template->refresh();

        return redirect()->route($this->builderFlow->nextRouteAfterBody($template), $template);
    }

    public function auth(Template $template, TemplatePreviewService $previewService): View|RedirectResponse
    {
        if (! $this->builderFlow->isAuthentication($template)) {
            return redirect()->route('templates.builder.header', $template);
        }

        return $this->stepView($template, 'auth', $previewService);
    }

    public function saveAuth(SaveAuthRequest $request, Template $template, TemplateBuilderService $builderService): RedirectResponse
    {
        $apps = collect($request->input('supported_apps', []))
            ->filter(fn ($app) => is_array($app) && filled($app['package_name'] ?? null) && filled($app['signature_hash'] ?? null))
            ->values()
            ->all();

        $builderService->saveStep($template, 'auth', [
            'enabled' => true,
            'copy_button_text' => (string) $request->input('copy_button_text', 'Copy Code'),
            'auto_fill' => $request->boolean('auto_fill'),
            'zero_tap' => $request->boolean('zero_tap'),
            'fill_button_text' => (string) $request->input('fill_button_text', 'Autofill'),
            'message_validity' => $request->boolean('message_validity'),
            'validity_seconds' => (int) $request->input('validity_seconds', 120),
            'expiration_time' => $request->boolean('expiration_time'),
            'expiration_minutes' => (int) $request->input('expiration_minutes', 120),
            'add_secret_recommendation' => $request->boolean('add_secret_recommendation'),
            'supported_apps' => $apps,
        ]);

        return redirect()->route($this->builderFlow->nextRouteAfterAuth(), $template);
    }

    public function lto(Template $template, TemplatePreviewService $previewService): View|RedirectResponse
    {
        if (! $this->builderFlow->isLto($template)) {
            return redirect()->route('templates.builder.header', $template);
        }

        return $this->stepView($template, 'lto', $previewService);
    }

    public function saveLto(SaveLtoRequest $request, Template $template, TemplateBuilderService $builderService): RedirectResponse
    {
        $builderService->saveStep($template, 'lto', [
            'enabled' => true,
            'discount_introduction' => (string) $request->input('discount_introduction'),
            'expiration_time' => $request->boolean('expiration_time'),
            'time_variable' => $request->boolean('expiration_time') ? (int) $request->input('time_variable', 60) : null,
            'coupon_code' => (string) $request->input('coupon_code', ''),
        ]);

        return redirect()->route($this->builderFlow->nextRouteAfterLto(), $template);
    }

    public function carousel(Template $template, TemplatePreviewService $previewService): View|RedirectResponse
    {
        if (! $this->builderFlow->isCarousel($template)) {
            return redirect()->route('templates.builder.header', $template);
        }

        abort_unless($this->builderFlow->canUseCarousel(), 403);

        return $this->stepView($template, 'carousel', $previewService, [
            'minCards' => (int) config('templates.carousel_min_cards', 2),
            'maxCards' => (int) config('templates.carousel_max_cards', 10),
        ]);
    }

    public function saveCarousel(SaveCarouselRequest $request, Template $template, TemplateBuilderService $builderService): RedirectResponse
    {
        abort_unless($this->builderFlow->canUseCarousel(), 403);

        $cards = collect($request->input('cards', []))
            ->map(function (array $card): array {
                $buttons = collect($card['buttons'] ?? [])
                    ->filter(fn ($btn) => is_array($btn) && filled($btn['text'] ?? null))
                    ->map(fn (array $btn): array => [
                        'text' => (string) $btn['text'],
                        'type' => (string) ($btn['type'] ?? 'QUICK_REPLY'),
                        'url' => (string) ($btn['url'] ?? ''),
                    ])
                    ->values()
                    ->all();

                return [
                    'header' => (string) ($card['header'] ?? 'IMAGE'),
                    'body' => (string) ($card['body'] ?? ''),
                    'media_url' => (string) ($card['media_url'] ?? ''),
                    'buttons' => $buttons,
                ];
            })
            ->values()
            ->all();

        $builderService->saveStep($template, 'carousel', [
            'enabled' => true,
            'cards' => $cards,
        ]);

        return redirect()->route($this->builderFlow->nextRouteAfterCarousel(), $template);
    }

    public function footer(Template $template, TemplatePreviewService $previewService): View|RedirectResponse
    {
        return $this->stepView($template, 'footer', $previewService);
    }

    public function saveFooter(SaveFooterRequest $request, Template $template, TemplateBuilderService $builderService): RedirectResponse
    {
        $builderService->saveStep($template, 'footer', [
            'text' => (string) $request->input('footer_text', ''),
        ]);

        return redirect()->route('templates.builder.buttons', $template);
    }

    public function buttons(
        Template $template,
        TemplatePreviewService $previewService,
        WhatsappFlowInteractiveService $flowInteractiveService,
    ): View|RedirectResponse {
        return $this->stepView($template, 'buttons', $previewService, [
            'whatsappFlows' => $flowInteractiveService->templatePickerOptions(),
        ]);
    }

    public function saveButtons(
        SaveButtonsRequest $request,
        Template $template,
        TemplateBuilderService $builderService,
        WhatsappFlowInteractiveService $flowInteractiveService,
    ): RedirectResponse {
        $mode = (string) $request->input('button_mode', 'none');

        if ($mode === 'lto') {
            $payload = $template->wizardPayload();
            $coupon = (string) ($payload['lto']['coupon_code'] ?? '');

            $buttons = [[
                'text' => (string) $request->input('buttons.0.text', 'Copy offer code'),
                'type' => 'copy_code',
                'url' => $coupon,
            ]];
        } elseif ($mode === 'none') {
            $buttons = [];
        } else {
            $buttons = collect($request->input('buttons', []))
                ->filter(fn ($button) => is_array($button) && filled($button['text'] ?? null))
                ->map(function (array $button) use ($flowInteractiveService): array {
                    $type = (string) ($button['type'] ?? 'url');
                    $result = [
                        'text' => (string) $button['text'],
                        'type' => $type,
                        'url' => $type === 'flow' ? '' : (string) ($button['url'] ?? ''),
                        'flow_id' => '',
                        'navigate_screen' => '',
                    ];

                    if ($type !== 'flow') {
                        return $result;
                    }

                    $internalOrMetaId = (string) ($button['flow_id'] ?? ($button['url'] ?? ''));
                    $resolved = $flowInteractiveService->resolveTemplateButtonFlow($internalOrMetaId);

                    if ($resolved !== null) {
                        $result['flow_id'] = $resolved['flow_id'];
                        $result['navigate_screen'] = $resolved['navigate_screen'];
                    } else {
                        $result['flow_id'] = $internalOrMetaId;
                    }

                    return $result;
                })
                ->values()
                ->all();
        }

        $builderService->saveStep($template, 'buttons', [
            'button_mode' => $mode,
            'is_opt_out' => false,
            'buttons' => $buttons,
        ]);

        return redirect()->route('templates.builder.submit', $template);
    }

    public function submit(Template $template, TemplatePreviewService $previewService): View|RedirectResponse
    {
        return $this->stepView($template, 'submit', $previewService);
    }

    public function saveSubmit(SaveSubmitRequest $request, Template $template, TemplateBuilderService $builderService): RedirectResponse
    {
        $builderService->submit($template);

        return redirect()
            ->route('templates.index')
            ->with('status', 'Template submitted for approval.');
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function stepView(
        Template $template,
        string $step,
        TemplatePreviewService $previewService,
        array $extra = [],
    ): View|RedirectResponse {
        if ($step !== 'body' && ! $template->isSetupComplete()) {
            return redirect()->route('templates.builder.body', $template);
        }

        $payload = $template->wizardPayload();

        return view('templates.builder.'.$step, array_merge([
            'template' => $template,
            'payload' => $payload,
            'previewData' => $previewService->forTemplate($template),
            'setupComplete' => $template->isSetupComplete(),
            'builderSteps' => $this->builderFlow->stepsFor($template),
            'canUseCarousel' => $this->builderFlow->canUseCarousel(),
        ], $extra));
    }
}
