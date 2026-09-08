<?php

declare(strict_types=1);

namespace App\Domains\Drip\Http\Requests;

use App\Domains\Drip\Support\DripNodeValidator;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SaveDripFlowDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nodes' => ['required', 'array'],
            'nodes.*.id' => ['required', 'string', 'max:64'],
            'nodes.*.type' => ['required', 'string'],
            'nodes.*.data' => ['sometimes', 'array'],
            'edges' => ['sometimes', 'array'],
            'edges.*.id' => ['sometimes', 'string'],
            'edges.*.source' => ['required_with:edges', 'string'],
            'edges.*.target' => ['required_with:edges', 'string'],
            'edges.*.sourceHandle' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $nodes = (array) $this->input('nodes', []);
            $nodeErrors = DripNodeValidator::validateNodes($nodes);

            foreach ($nodeErrors as $key => $messages) {
                foreach ($messages as $message) {
                    $validator->errors()->add($key, $message);
                }
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $validator->errors()->first() ?: 'Flow validation failed.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
