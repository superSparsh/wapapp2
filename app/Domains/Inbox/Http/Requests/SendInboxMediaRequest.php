<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Http\Requests;

use App\Support\WhatsappMediaRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SendInboxMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $type = (string) $this->input('media_type', 'image');
        if (! in_array($type, WhatsappMediaRules::types(), true)) {
            $type = 'image';
        }

        return [
            'media_type' => ['required', 'string', Rule::in(WhatsappMediaRules::types())],
            'file' => WhatsappMediaRules::fileRules($type),
            'caption' => ['nullable', 'string', 'max:1024'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $type = (string) $this->input('media_type');
            try {
                WhatsappMediaRules::assertValid($this->file('file'), $type, 'file');
            } catch (\Illuminate\Validation\ValidationException $e) {
                foreach ($e->errors() as $key => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($key, $message);
                    }
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $type = (string) $this->input('media_type', 'image');
        if (! in_array($type, WhatsappMediaRules::types(), true)) {
            $type = 'image';
        }

        return array_merge(
            [
                'media_type.required' => 'Please select a media type (image, video, audio, or document).',
                'media_type.in' => 'Please select a valid media type (image, video, audio, or document).',
            ],
            WhatsappMediaRules::validationMessages($type),
        );
    }
}
