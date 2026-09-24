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
        $type = (string) $this->input('media_type', '');

        return [
            'media_type' => ['required', 'string', Rule::in(WhatsappMediaRules::types())],
            'file' => WhatsappMediaRules::fileRules($type !== '' ? $type : 'image'),
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
        $type = (string) $this->input('media_type', 'file');
        $hint = in_array($type, WhatsappMediaRules::types(), true)
            ? WhatsappMediaRules::hint($type)
            : 'Check the allowed format and size for this media type.';

        return [
            'file.required' => 'Please choose a file to upload.',
            'file.mimes' => 'Invalid file format. '.$hint,
            'file.max' => 'File is too large. '.$hint,
        ];
    }
}
