<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SaveFlowDataRequest extends FormRequest
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
        if ($this->filled('customData')) {
            return [
                'customData' => ['required'],
            ];
        }

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

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'status' => 'error',
            'message' => $validator->errors()->first() ?: 'Flow validation failed.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
