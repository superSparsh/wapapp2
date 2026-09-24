<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SendInboxMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $type = (string) $this->input('media_type', 'image');
        $limits = [
            'image' => 5120,
            'video' => 14336,
            'document' => 14336,
            'audio' => 14336,
        ];
        $mimes = [
            'image' => 'jpeg,jpg,png,webp',
            'video' => 'mp4,3gp',
            'document' => 'pdf,docx,xlsx,pptx,txt',
            'audio' => 'mp3,ogg,amr,aac,m4a',
        ];

        $maxKb = $limits[$type] ?? 14336;
        $mimeList = $mimes[$type] ?? $mimes['image'];

        return [
            'file' => ['nullable', 'file', 'mimes:'.$mimeList, 'max:'.$maxKb],
            'media_url' => ['nullable', 'url', 'max:2048'],
            'media_type' => ['required', 'string', Rule::in(['image', 'video', 'audio', 'document'])],
            'caption' => ['nullable', 'string', 'max:1024'],
            'file_name' => ['nullable', 'string', 'max:255'],
            'file_type' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->hasFile('file') && blank($this->input('media_url'))) {
                $validator->errors()->add('file', 'Provide a file upload or a media URL.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimes' => 'Invalid file format for this media type.',
            'file.max' => 'File exceeds the maximum size for this media type.',
        ];
    }
}
