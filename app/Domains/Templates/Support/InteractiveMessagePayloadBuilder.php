<?php

declare(strict_types=1);

namespace App\Domains\Templates\Support;

use App\Domains\WhatsappFlow\Services\WhatsappFlowInteractiveService;
use App\Models\InteractiveMessage;
use App\Models\WhatsappFlow;
use App\Support\PublicId;
use Illuminate\Support\Str;

/**
 * Maps Free Template (InteractiveMessage) storage + chatbot node data
 * onto WhatsApp / Alibaba CAMS interactive Content JSON.
 */
final class InteractiveMessagePayloadBuilder
{
    public function __construct(
        private readonly WhatsappFlowInteractiveService $flowInteractiveService,
    ) {}

    /**
     * @param  callable(string): string|null  $resolveText
     * @return array<string, mixed>
     */
    public function forMessage(InteractiveMessage $message, ?callable $resolveText = null): array
    {
        return $this->fromFlat((string) $message->type, $message->normalizedContent(), $resolveText);
    }

    /**
     * Shape used by chatbot builder "load saved message" picker.
     *
     * @return array<string, mixed>
     */
    public function toLibraryContent(InteractiveMessage $message): array
    {
        $flat = $message->normalizedContent();
        $api = $this->fromFlat((string) $message->type, $flat);
        $type = (string) $message->type;

        $buttons = [];
        foreach (array_values($flat['buttons'] ?? []) as $index => $button) {
            if (! is_array($button)) {
                continue;
            }
            $title = (string) ($button['title'] ?? $button['text'] ?? $button['label'] ?? '');
            if ($title === '') {
                continue;
            }
            $buttons[] = [
                'id' => (string) ($button['id'] ?? 'btn_'.$index),
                'title' => $title,
                'text' => $title,
            ];
        }

        $sections = [];
        foreach (array_values($flat['list_sections'] ?? []) as $sectionIndex => $section) {
            if (! is_array($section)) {
                continue;
            }
            $rows = [];
            foreach (array_values($section['rows'] ?? []) as $rowIndex => $row) {
                if (! is_array($row) || blank($row['title'] ?? null)) {
                    continue;
                }
                $rows[] = [
                    'id' => (string) ($row['id'] ?? 'row_'.$sectionIndex.'_'.$rowIndex),
                    'title' => (string) $row['title'],
                    'description' => (string) ($row['description'] ?? ''),
                ];
            }
            $sections[] = [
                'title' => (string) ($section['title'] ?? ''),
                'rows' => $rows,
            ];
        }

        return [
            // Flat / UI keys (ReactFlowInteractiveMessageModule)
            'interactiveType' => $type,
            'bodyText' => (string) ($flat['body'] ?? ''),
            'footerText' => (string) ($flat['footer'] ?? ''),
            'buttonText' => (string) ($flat['list_button_text'] ?? 'View options'),
            'buttons' => $buttons,
            'sections' => $sections,
            'catalog_id' => (string) ($flat['catalog_id'] ?? ''),
            'product_retailer_id' => (string) ($flat['product_retailer_id'] ?? ''),
            'flow_id' => (string) ($flat['flow_id'] ?? ''),
            'flow_cta' => (string) ($flat['flow_cta'] ?? 'Open'),
            'headerType' => (string) (($flat['header']['type'] ?? 'none') ?: 'none'),
            'headerText' => (string) ($flat['header']['text'] ?? ''),
            'list_button_text' => (string) ($flat['list_button_text'] ?? ''),
            'list_sections' => $flat['list_sections'] ?? [],
            // API-ready keys (CAMS / InboxOutboundService)
            'type' => $api['type'] ?? $type,
            'header' => $api['header'] ?? null,
            'body' => $api['body'] ?? null,
            'footer' => $api['footer'] ?? null,
            'action' => $api['action'] ?? null,
        ];
    }

    /**
     * Build CAMS interactive JSON from free-template flat content.
     *
     * @param  array<string, mixed>  $content
     * @param  callable(string): string|null  $resolveText
     * @return array<string, mixed>
     */
    public function fromFlat(string $type, array $content, ?callable $resolveText = null): array
    {
        $resolve = $resolveText ?? static fn (string $text): string => $text;
        $type = strtolower(trim($type));
        $bodyText = $resolve(trim((string) ($content['body'] ?? $content['bodyText'] ?? '')));
        $footerText = $resolve(trim((string) ($content['footer'] ?? $content['footerText'] ?? '')));

        $payload = [
            'type' => $type === 'product' ? 'product' : $type,
        ];

        if ($bodyText !== '') {
            $payload['body'] = ['text' => $bodyText];
        }

        if ($footerText !== '' && ! in_array($type, ['product', 'flow'], true)) {
            $payload['footer'] = ['text' => $footerText];
        }

        $header = is_array($content['header'] ?? null) ? $content['header'] : null;
        if ($header === null && filled($content['header_text'] ?? $content['headerText'] ?? null)) {
            $header = [
                'type' => 'text',
                'text' => (string) ($content['header_text'] ?? $content['headerText']),
            ];
        } elseif (is_string($content['header'] ?? null) && trim((string) $content['header']) !== '') {
            $header = [
                'type' => 'text',
                'text' => (string) $content['header'],
            ];
        }

        if (is_array($header)) {
            $headerType = strtolower((string) ($header['type'] ?? 'none'));
            if ($headerType === 'text' && filled($header['text'] ?? null)) {
                $payload['header'] = [
                    'type' => 'text',
                    'text' => $resolve((string) $header['text']),
                ];
            }
        }

        $payload['action'] = match ($type) {
            'list' => $this->listAction($content),
            'product' => $this->productAction($content),
            'product_list' => $this->productListAction($content),
            'catalog_message' => $this->catalogAction($content),
            'cta_url' => $this->ctaUrlAction($content),
            'location_request_message' => ['name' => 'send_location'],
            'address_message' => $this->addressAction($content),
            'flow' => $this->flowAction($content, $bodyText !== '' ? $bodyText : 'Tap below to continue'),
            default => $this->buttonAction($content),
        };

        if ($type === 'flow') {
            // flowAction already returns full interactive content when resolved via service
            if (isset($payload['action']['_full'])) {
                $full = $payload['action']['_full'];
                unset($payload['action']['_full']);

                return $full;
            }
        }

        return $payload;
    }

    /**
     * Build from chatbot / drip node data (flat and/or already API-shaped).
     *
     * @param  array<string, mixed>  $data
     * @param  callable(string): string|null  $resolveText
     * @return array<string, mixed>|null
     */
    public function fromNodeData(array $data, ?callable $resolveText = null): ?array
    {
        if (isset($data['action'], $data['type']) && is_array($data['action'])) {
            $resolve = $resolveText ?? static fn (string $text): string => $text;
            $content = [
                'type' => (string) $data['type'],
                'action' => $data['action'],
            ];

            foreach (['header', 'body', 'footer'] as $section) {
                if (! isset($data[$section]) || ! is_array($data[$section])) {
                    continue;
                }
                $sectionData = $data[$section];
                if (isset($sectionData['text']) && is_string($sectionData['text'])) {
                    $sectionData['text'] = $resolve($sectionData['text']);
                }
                $content[$section] = $sectionData;
            }

            if (! isset($content['body']) && filled($data['bodyText'] ?? $data['text'] ?? null)) {
                $content['body'] = ['text' => $resolve((string) ($data['bodyText'] ?? $data['text']))];
            }

            if (($content['type'] ?? '') === 'flow') {
                $parameters = $content['action']['parameters'] ?? null;
                if (is_array($parameters) && blank($parameters['flow_token'] ?? null) && filled($parameters['flow_id'] ?? null)) {
                    $parameters['flow_token'] = $this->flowInteractiveService->generateFlowToken((string) $parameters['flow_id']);
                    $content['action']['parameters'] = $parameters;
                }
            }

            return $content;
        }

        $type = strtolower((string) ($data['interactiveType'] ?? $data['interactive_type'] ?? $data['type'] ?? 'button'));

        $flat = [
            'body' => (string) ($data['bodyText'] ?? $data['text'] ?? $data['message'] ?? ''),
            'footer' => (string) ($data['footerText'] ?? $data['footer'] ?? ''),
            'buttons' => $data['buttons'] ?? $data['options'] ?? [],
            'list_button_text' => (string) ($data['buttonText'] ?? $data['list_button_text'] ?? 'View options'),
            'list_sections' => $data['sections'] ?? $data['list_sections'] ?? $data['listItems'] ?? [],
            'catalog_id' => (string) ($data['catalog_id'] ?? ''),
            'product_retailer_id' => (string) ($data['product_retailer_id'] ?? ''),
            'flow_id' => (string) ($data['flow_id'] ?? $data['flowId'] ?? ''),
            'flow_cta' => (string) ($data['flow_cta'] ?? $data['flowCta'] ?? 'Open'),
            'header' => is_array($data['header'] ?? null) ? $data['header'] : [
                'type' => (string) ($data['headerType'] ?? 'none'),
                'text' => (string) ($data['headerText'] ?? ''),
            ],
        ];

        // Normalize options-as-strings into button objects for drip nodes.
        if ($type === 'button' && $flat['buttons'] !== [] && ! is_array($flat['buttons'][0] ?? null)) {
            $flat['buttons'] = array_map(
                static fn ($title, $index): array => [
                    'id' => 'btn_'.$index,
                    'title' => (string) $title,
                ],
                $flat['buttons'],
                array_keys($flat['buttons']),
            );
        }

        if ($type === 'button' && $this->emptyButtons($flat['buttons'])) {
            return null;
        }

        if ($type === 'list' && $this->emptyListSections($flat['list_sections'])) {
            return null;
        }

        if ($flat['body'] === '' && $type !== 'product') {
            return null;
        }

        return $this->fromFlat($type, $flat, $resolveText);
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function buttonAction(array $content): array
    {
        $buttons = [];
        foreach (array_values($content['buttons'] ?? []) as $index => $button) {
            if (! is_array($button)) {
                continue;
            }
            $title = trim((string) ($button['title'] ?? $button['text'] ?? $button['label'] ?? ''));
            if ($title === '') {
                continue;
            }
            $buttons[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => (string) ($button['id'] ?? 'btn_'.$index),
                    'title' => Str::limit($title, 20, ''),
                ],
            ];
        }

        return ['buttons' => array_slice($buttons, 0, 3)];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array{button: string, sections: list<array<string, mixed>>}
     */
    private function listAction(array $content): array
    {
        $sections = [];
        foreach (array_values($content['list_sections'] ?? $content['sections'] ?? []) as $sectionIndex => $section) {
            if (! is_array($section)) {
                continue;
            }
            $rows = [];
            foreach (array_values($section['rows'] ?? $section['items'] ?? []) as $rowIndex => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $title = trim((string) ($row['title'] ?? $row['text'] ?? $row['label'] ?? ''));
                if ($title === '') {
                    continue;
                }
                $rows[] = array_filter([
                    'id' => (string) ($row['id'] ?? 'row_'.$sectionIndex.'_'.$rowIndex),
                    'title' => Str::limit($title, 24, ''),
                    'description' => filled($row['description'] ?? null)
                        ? Str::limit((string) $row['description'], 72, '')
                        : null,
                ], static fn (mixed $value): bool => $value !== null && $value !== '');
            }
            if ($rows === []) {
                continue;
            }
            $sections[] = array_filter([
                'title' => filled($section['title'] ?? null)
                    ? Str::limit((string) $section['title'], 24, '')
                    : null,
                'rows' => $rows,
            ], static fn (mixed $value): bool => $value !== null && $value !== '');
        }

        return [
            'button' => Str::limit((string) ($content['list_button_text'] ?? $content['buttonText'] ?? 'View options'), 20, '') ?: 'Options',
            'sections' => $sections,
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array{catalog_id: string, product_retailer_id: string}
     */
    private function productAction(array $content): array
    {
        return [
            'catalog_id' => (string) ($content['catalog_id'] ?? ''),
            'product_retailer_id' => (string) ($content['product_retailer_id'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array{catalog_id: string, sections: list<array{title: string, product_items: list<array{product_retailer_id: string}>}>}
     */
    private function productListAction(array $content): array
    {
        $ids = $content['product_retailer_ids'] ?? [];
        if (! is_array($ids) || $ids === []) {
            $ids = array_filter([(string) ($content['product_retailer_id'] ?? '')]);
        }

        $items = [];
        foreach (array_values($ids) as $id) {
            $retailerId = trim((string) $id);
            if ($retailerId === '') {
                continue;
            }
            $items[] = ['product_retailer_id' => $retailerId];
        }

        return [
            'catalog_id' => (string) ($content['catalog_id'] ?? ''),
            'sections' => [[
                'title' => Str::limit((string) ($content['section_title'] ?? $content['header_text'] ?? 'Products'), 24, '') ?: 'Products',
                'product_items' => $items,
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array{name: string, parameters?: array{thumbnail_product_retailer_id: string}}
     */
    private function catalogAction(array $content): array
    {
        $action = ['name' => 'catalog_message'];
        $thumbnail = trim((string) ($content['product_retailer_id'] ?? $content['thumbnail_product_retailer_id'] ?? ''));
        if ($thumbnail !== '') {
            $action['parameters'] = ['thumbnail_product_retailer_id' => $thumbnail];
        }

        return $action;
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array{name: string, parameters: array{display_text: string, url: string}}
     */
    private function ctaUrlAction(array $content): array
    {
        return [
            'name' => 'cta_url',
            'parameters' => [
                'display_text' => Str::limit((string) ($content['button_text'] ?? $content['display_text'] ?? 'Open'), 20, '') ?: 'Open',
                'url' => (string) ($content['url'] ?? ''),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array{name: string, parameters: array{country: string}}
     */
    private function addressAction(array $content): array
    {
        $country = strtoupper(trim((string) ($content['country'] ?? 'IN')));

        return [
            'name' => 'address_message',
            'parameters' => [
                'country' => $country !== '' ? $country : 'IN',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function flowAction(array $content, string $bodyText): array
    {
        $flowId = (string) ($content['flow_id'] ?? $content['flowId'] ?? '');
        $flowCta = (string) ($content['flow_cta'] ?? $content['flowCta'] ?? 'Open');

        $flow = $flowId !== '' ? $this->flowInteractiveService->findByIdentifier($flowId) : null;
        if (! $flow instanceof WhatsappFlow) {
            // Try uuid via PublicId when identifier lookup misses
            try {
                $flow = PublicId::find(WhatsappFlow::class, $flowId);
            } catch (\Throwable) {
                $flow = null;
            }
        }

        if ($flow instanceof WhatsappFlow && filled($flow->meta_flow_id)) {
            return [
                '_full' => $this->flowInteractiveService->buildFlowInteractiveContent(
                    $flow,
                    $bodyText !== '' ? $bodyText : 'Tap below to continue',
                    $flowCta !== '' ? $flowCta : 'Open',
                ),
            ];
        }

        return [
            'name' => 'flow',
            'parameters' => [
                'mode' => 'published',
                'flow_message_version' => '3',
                'flow_token' => $this->flowInteractiveService->generateFlowToken($flowId !== '' ? $flowId : 'flow'),
                'flow_id' => $flowId,
                'flow_cta' => $flowCta !== '' ? $flowCta : 'Open',
                'flow_action' => 'navigate',
                'flow_action_payload' => [
                    'screen' => 'SCREEN_1',
                    'data' => new \stdClass,
                ],
            ],
        ];
    }

    private function emptyButtons(mixed $buttons): bool
    {
        if (! is_array($buttons) || $buttons === []) {
            return true;
        }

        foreach ($buttons as $button) {
            if (is_array($button) && filled($button['title'] ?? $button['text'] ?? $button['label'] ?? null)) {
                return false;
            }
            if (is_string($button) && trim($button) !== '') {
                return false;
            }
        }

        return true;
    }

    private function emptyListSections(mixed $sections): bool
    {
        if (! is_array($sections) || $sections === []) {
            return true;
        }

        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }
            foreach ($section['rows'] ?? $section['items'] ?? [] as $row) {
                if (is_array($row) && filled($row['title'] ?? $row['text'] ?? null)) {
                    return false;
                }
            }
        }

        return true;
    }
}
