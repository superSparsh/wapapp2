<?php

declare(strict_types=1);

namespace App\Domains\Templates\Http\Controllers;

use App\Domains\Templates\Services\InteractiveMessagePreviewService;
use App\Domains\Templates\Services\InteractiveMessageService;
use App\Domains\WhatsappFlow\Services\WhatsappFlowQueryService;
use App\Domains\Templates\Support\InteractiveMessagePresenter;
use App\Http\Controllers\Controller;
use App\Models\InteractiveMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InteractiveMessageController extends Controller
{
    public function create(WhatsappFlowQueryService $flowQueryService): View
    {
        return view('templates.free.create', [
            'message' => null,
            'content' => [
                'body' => '',
                'footer' => '',
                'buttons' => [],
                'header' => ['type' => 'none', 'text' => '', 'media_path' => null],
            ],
            'previewData' => [
                'body' => '',
                'footer' => '',
                'buttons' => [],
                'header_type' => 'none',
                'header_text' => '',
                'header_image' => null,
            ],
            'whatsappFlows' => $flowQueryService->activeFlows(),
        ]);
    }

    public function store(Request $request, InteractiveMessageService $service): RedirectResponse
    {
        $message = $service->create($this->validatedPayload($request));

        return redirect()
            ->route('templates.index', ['tab' => 'free'])
            ->with('status', 'Free template message created.');
    }

    public function edit(
        InteractiveMessage $interactiveMessage,
        InteractiveMessagePreviewService $previewService,
        WhatsappFlowQueryService $flowQueryService,
    ): View {
        return view('templates.free.create', [
            'message' => $interactiveMessage,
            'content' => $interactiveMessage->normalizedContent(),
            'previewData' => $previewService->forMessage($interactiveMessage),
            'whatsappFlows' => $flowQueryService->activeFlows(),
        ]);
    }

    public function update(
        Request $request,
        InteractiveMessage $interactiveMessage,
        InteractiveMessageService $service,
    ): RedirectResponse {
        $service->update($interactiveMessage, $this->validatedPayload($request));

        return redirect()
            ->route('templates.index', ['tab' => 'free'])
            ->with('status', 'Free template message updated.');
    }

    public function destroy(InteractiveMessage $interactiveMessage, InteractiveMessageService $service): RedirectResponse
    {
        $service->delete($interactiveMessage);

        return redirect()
            ->route('templates.index', ['tab' => 'free'])
            ->with('status', 'Free template message deleted.');
    }

    public function preview(
        InteractiveMessage $interactiveMessage,
        InteractiveMessagePreviewService $previewService,
    ): View {
        return view('templates.free.preview', [
            'message' => $interactiveMessage,
            'previewData' => $previewService->forMessage($interactiveMessage),
        ]);
    }

    /** @return array<string, mixed> */
    private function validatedPayload(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'string', 'in:button,list,product,flow'],
            'body' => ['required', 'string', 'max:1024'],
            'footer' => ['nullable', 'string', 'max:60'],
            'buttons' => ['nullable', 'array', 'max:3'],
            'buttons.*.text' => ['required_with:buttons', 'string', 'max:20'],
            'buttons.*.type' => ['nullable', 'string', 'max:32'],
            'buttons.*.url' => ['nullable', 'string', 'max:255'],
            'list_button_text' => ['required_if:type,list', 'nullable', 'string', 'max:20'],
            'list_sections' => ['required_if:type,list', 'nullable', 'array', 'min:1'],
            'list_sections.*.title' => ['required_with:list_sections', 'string', 'max:24'],
            'list_sections.*.rows' => ['required_with:list_sections', 'array', 'min:1'],
            'list_sections.*.rows.*.title' => ['required_with:list_sections', 'string', 'max:24'],
            'list_sections.*.rows.*.description' => ['nullable', 'string', 'max:72'],
            'catalog_id' => ['required_if:type,product', 'nullable', 'string', 'max:120'],
            'product_retailer_id' => ['required_if:type,product', 'nullable', 'string', 'max:120'],
            'flow_id' => ['required_if:type,flow', 'nullable', 'string', 'max:120'],
            'flow_cta' => ['required_if:type,flow', 'nullable', 'string', 'max:20'],
        ]);

        $type = (string) $validated['type'];

        $buttons = collect($validated['buttons'] ?? [])
            ->filter(fn ($button) => is_array($button) && filled($button['text'] ?? null))
            ->map(fn (array $button): array => [
                'text' => (string) $button['text'],
                'type' => (string) ($button['type'] ?? 'quick_reply'),
                'url' => (string) ($button['url'] ?? ''),
            ])
            ->values()
            ->all();

        $content = [
            'body' => $validated['body'],
            'footer' => $validated['footer'] ?? '',
            'buttons' => $type === 'button' ? $buttons : [],
            'header' => ['type' => 'none', 'text' => '', 'media_path' => null],
        ];

        if ($type === 'list') {
            $content['list_button_text'] = (string) ($validated['list_button_text'] ?? '');
            $content['list_sections'] = collect($validated['list_sections'] ?? [])
                ->map(fn (array $section): array => [
                    'title' => (string) ($section['title'] ?? ''),
                    'rows' => collect($section['rows'] ?? [])
                        ->filter(fn ($row) => is_array($row) && filled($row['title'] ?? null))
                        ->map(fn (array $row): array => [
                            'title' => (string) $row['title'],
                            'description' => (string) ($row['description'] ?? ''),
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all();
        }

        if ($type === 'product') {
            $content['catalog_id'] = (string) ($validated['catalog_id'] ?? '');
            $content['product_retailer_id'] = (string) ($validated['product_retailer_id'] ?? '');
        }

        if ($type === 'flow') {
            $content['flow_id'] = (string) ($validated['flow_id'] ?? '');
            $content['flow_cta'] = (string) ($validated['flow_cta'] ?? '');
        }

        return [
            'name' => $validated['name'],
            'type' => $type,
            'content' => $content,
        ];
    }
}
