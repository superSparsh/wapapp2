<?php

declare(strict_types=1);

namespace App\Domains\Templates\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveButtonsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxButtons = (int) config('templates.max_buttons', 10);
        $textLimit = (int) config('templates.button_text_limit', 25);
        $urlLimit = (int) config('templates.button_url_limit', 2000);

        return [
            'button_mode' => ['required', 'in:none,call_to_action,quick_reply,whatsapp_flows,mixed,authentication,lto'],
            'is_opt_out' => ['nullable', 'boolean'],
            'buttons' => ['nullable', 'array', "max:{$maxButtons}"],
            'buttons.*.text' => ['nullable', 'string', "max:{$textLimit}"],
            'buttons.*.type' => ['nullable', 'in:url,phone,unsubscribe,quick_reply,flow,copy_code'],
            'buttons.*.url' => ['nullable', 'string', "max:{$urlLimit}"],
            'buttons.*.flow_id' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $mode = (string) $this->input('button_mode', 'none');

            if ($mode === 'none') {
                return;
            }

            $buttons = collect($this->input('buttons', []))
                ->filter(fn ($button) => is_array($button) && filled($button['text'] ?? null))
                ->values();

            if ($buttons->isEmpty()) {
                return;
            }

            $maxUrl = (int) config('templates.max_url_buttons', 2);
            $maxPhone = (int) config('templates.max_phone_buttons', 1);

            $urlCount = $buttons->whereIn('type', ['url', 'unsubscribe'])->count();
            $phoneCount = $buttons->where('type', 'phone')->count();

            if ($urlCount > $maxUrl) {
                $validator->errors()->add('buttons', "Maximum {$maxUrl} URL buttons allowed.");
            }

            if ($phoneCount > $maxPhone) {
                $validator->errors()->add('buttons', "Maximum {$maxPhone} phone buttons allowed.");
            }

            if (in_array($mode, ['quick_reply', 'mixed'], true)) {
                $qrTexts = $buttons
                    ->where('type', 'quick_reply')
                    ->pluck('text')
                    ->map(fn ($text) => strtolower(trim((string) $text)))
                    ->all();

                if (count($qrTexts) !== count(array_unique($qrTexts))) {
                    $validator->errors()->add('buttons', 'Quick reply button texts must be unique.');
                }
            }

            if ($mode === 'mixed') {
                $ctaCount = $buttons->whereIn('type', ['url', 'phone', 'unsubscribe'])->count();
                $qrCount = $buttons->where('type', 'quick_reply')->count();

                if ($ctaCount < 1 || $qrCount < 1) {
                    $validator->errors()->add('buttons', 'Mixed mode requires at least one CTA button and one quick reply button.');
                }
            }

            if ($mode === 'whatsapp_flows') {
                $flowButtons = $buttons->where('type', 'flow');

                if ($flowButtons->count() > 1) {
                    $validator->errors()->add('buttons', 'Only one WhatsApp Flow button is allowed.');
                }

                $flowButton = $flowButtons->first();
                if ($flowButton && filled($flowButton['text']) && blank($flowButton['flow_id'] ?? null)) {
                    $validator->errors()->add('buttons', 'Please select a flow for the flow button.');
                }
            }
        });
    }
}
