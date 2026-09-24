<?php

declare(strict_types=1);

namespace App\Domains\Templates\Http\Requests;

use App\Domains\Templates\Http\Requests\Concerns\ValidatesEditableTemplateName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveCarouselRequest extends FormRequest
{
    use ValidatesEditableTemplateName;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $cards = $this->input('cards', []);
        if (! is_array($cards)) {
            return;
        }

        $normalized = [];
        foreach ($cards as $card) {
            if (! is_array($card)) {
                continue;
            }

            $buttons = collect($card['buttons'] ?? [])
                ->filter(fn ($btn) => is_array($btn) && filled(trim((string) ($btn['text'] ?? ''))))
                ->map(fn (array $btn): array => [
                    'text' => trim((string) ($btn['text'] ?? '')),
                    'type' => strtoupper(trim((string) ($btn['type'] ?? 'QUICK_REPLY'))),
                    'url' => trim((string) ($btn['url'] ?? '')),
                ])
                ->values()
                ->all();

            $card['buttons'] = $buttons;
            $card['use_url'] = filter_var($card['use_url'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $normalized[] = $card;
        }

        $this->merge(['cards' => $normalized]);
    }

    public function rules(): array
    {
        $min = (int) config('templates.carousel_min_cards', 2);
        $max = (int) config('templates.carousel_max_cards', 10);
        $bodyLimit = (int) config('templates.carousel_card_body_limit', 150);
        $buttonTextLimit = (int) config('templates.carousel_button_text_limit', 25);

        return array_merge($this->editableNameRules(), [
            'cards' => ['required', 'array', "min:{$min}", "max:{$max}"],
            'carousel_body' => ['nullable', 'string', 'max:'.(int) config('templates.body_limit', 1024)],
            'cards.*.body' => ['required', 'string', "max:{$bodyLimit}"],
            'cards.*.header' => ['required', 'string', 'in:IMAGE,VIDEO'],
            'cards.*.media_path' => ['nullable', 'string', 'max:500'],
            'cards.*.media_url' => ['nullable', 'string', 'max:2048'],
            'cards.*.use_url' => ['nullable', 'boolean'],
            'cards.*.buttons' => ['required', 'array', 'min:1', 'max:2'],
            'cards.*.buttons.*.text' => ['nullable', 'string', "max:{$buttonTextLimit}"],
            'cards.*.buttons.*.type' => ['nullable', 'string', 'in:QUICK_REPLY,URL,PHONE_NUMBER'],
            'cards.*.buttons.*.url' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    public function messages(): array
    {
        $bodyLimit = (int) config('templates.carousel_card_body_limit', 150);

        return array_merge($this->editableNameMessages(), [
            'cards.required' => 'At least one card is required.',
            'cards.min' => 'Carousel needs at least :min cards.',
            'cards.max' => 'Carousel can have at most :max cards.',
            'cards.*.header.required' => 'Header type is required for each card.',
            'cards.*.body.required' => 'Body text is required for each card.',
            'cards.*.body.max' => "Body must be {$bodyLimit} characters or less.",
            'cards.*.buttons.required' => 'Each card must have at least one button.',
            'cards.*.buttons.min' => 'Each card must have at least one button.',
            'cards.*.buttons.max' => 'A card can have a maximum of 2 buttons only.',
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateEditableNameUnique($validator);

            $cards = $this->input('cards', []);
            if (! is_array($cards) || $cards === []) {
                return;
            }

            $referenceHeader = null;
            $referenceTypes = null;
            $referenceCount = null;
            $qrTextsGlobal = [];

            foreach ($cards as $index => $card) {
                if (! is_array($card)) {
                    continue;
                }

                $cardLabel = 'Card '.($index + 1);
                $useUrl = filter_var($card['use_url'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $path = trim((string) ($card['media_path'] ?? ''));
                $url = trim((string) ($card['media_url'] ?? ''));
                $header = strtoupper(trim((string) ($card['header'] ?? '')));

                if ($useUrl) {
                    if ($url === '') {
                        $validator->errors()->add("cards.{$index}.media_url", "{$cardLabel}: Header media URL is required.");
                    }
                } elseif ($path === '') {
                    $validator->errors()->add("cards.{$index}.media_path", "{$cardLabel}: Header media is required.");
                }

                if ($header !== '' && in_array($header, ['IMAGE', 'VIDEO'], true)) {
                    if ($referenceHeader === null) {
                        $referenceHeader = $header;
                    } elseif ($header !== $referenceHeader) {
                        $validator->errors()->add(
                            "cards.{$index}.header",
                            "{$cardLabel}: Header type must match other cards ({$referenceHeader}).",
                        );
                    }
                }

                $buttons = collect($card['buttons'] ?? [])
                    ->filter(fn ($btn) => is_array($btn) && filled(trim((string) ($btn['text'] ?? ''))))
                    ->values()
                    ->all();

                if ($buttons === []) {
                    $validator->errors()->add("cards.{$index}.buttons", "{$cardLabel}: At least one button is required.");
                    continue;
                }

                if (count($buttons) > 2) {
                    $validator->errors()->add("cards.{$index}.buttons", "{$cardLabel}: No more than 2 buttons allowed.");
                }

                $types = [];
                foreach ($buttons as $buttonIndex => $button) {
                    $type = strtoupper(trim((string) ($button['type'] ?? 'QUICK_REPLY')));
                    $text = trim((string) ($button['text'] ?? ''));
                    $value = trim((string) ($button['url'] ?? ''));
                    $types[] = $type;

                    if ($text === '') {
                        $validator->errors()->add(
                            "cards.{$index}.buttons.{$buttonIndex}.text",
                            "{$cardLabel}: All button texts must be filled.",
                        );
                    }

                    if (in_array($type, ['URL', 'PHONE_NUMBER'], true) && $value === '') {
                        $fieldLabel = $type === 'PHONE_NUMBER' ? 'phone number' : 'website URL';
                        $validator->errors()->add(
                            "cards.{$index}.buttons.{$buttonIndex}.url",
                            "{$cardLabel}: Button \"{$text}\" needs a {$fieldLabel}.",
                        );
                    }

                    if ($type === 'QUICK_REPLY' && $text !== '') {
                        $key = mb_strtolower($text);
                        if (isset($qrTextsGlobal[$key])) {
                            $validator->errors()->add(
                                "cards.{$index}.buttons.{$buttonIndex}.text",
                                "{$cardLabel}: Quick reply button text \"{$text}\" is already used in another card.",
                            );
                        } else {
                            $qrTextsGlobal[$key] = true;
                        }
                    }
                }

                if ($referenceTypes === null) {
                    $referenceTypes = $types;
                    $referenceCount = count($types);
                } else {
                    if (count($types) !== $referenceCount) {
                        $validator->errors()->add(
                            "cards.{$index}.buttons",
                            "{$cardLabel}: Button count does not match the first card.",
                        );
                    }

                    if ($types !== $referenceTypes) {
                        $validator->errors()->add(
                            "cards.{$index}.buttons",
                            "{$cardLabel}: Button types do not match the first card. All cards must use the same button types in the same order.",
                        );
                    }
                }
            }
        });
    }
}
