<?php

declare(strict_types=1);

namespace App\Domains\Templates\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveCarouselRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $min = (int) config('templates.carousel_min_cards', 2);
        $max = (int) config('templates.carousel_max_cards', 10);

        return [
            'cards' => ['required', 'array', "min:{$min}", "max:{$max}"],
            'cards.*.body' => ['required', 'string', 'max:160'],
            'cards.*.header' => ['nullable', 'string', 'in:IMAGE,VIDEO'],
            'cards.*.media_url' => ['nullable', 'string', 'max:2048'],
            'cards.*.buttons' => ['nullable', 'array', 'max:2'],
            'cards.*.buttons.*.text' => ['required_with:cards.*.buttons', 'string', 'max:25'],
            'cards.*.buttons.*.type' => ['nullable', 'string', 'in:QUICK_REPLY,URL'],
            'cards.*.buttons.*.url' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
