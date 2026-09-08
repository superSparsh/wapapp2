<?php

declare(strict_types=1);

namespace App\Domains\Templates\Http\Requests;

use App\Models\Template;
use Illuminate\Foundation\Http\FormRequest;

class SaveHeaderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = (string) $this->input('header_type', 'none');

        $rules = [
            'header_type' => ['required', 'in:none,text,image,video,document,audio,location'],
            'header_text' => ['nullable', 'string', 'max:'.config('templates.header_text_limit', 60)],
            'media_url' => ['nullable', 'string'],
            'doc_name' => ['nullable', 'string', 'max:255'],
            'use_url' => ['nullable', 'boolean'],
        ];

        if ($type === 'none') {
            return $rules;
        }

        if ($type === 'text') {
            $rules['header_text'] = ['required', 'string', 'max:'.config('templates.header_text_limit', 60)];
        }

        if ($this->boolean('use_url') && in_array($type, ['image', 'video', 'document', 'audio'], true)) {
            $mediaUrl = (string) $this->input('media_url', '');

            if ($mediaUrl !== '' && (preg_match('/\{\{[a-zA-Z0-9_]+\}\}/', $mediaUrl) || preg_match('/\$\([a-zA-Z0-9_]+\)/', $mediaUrl))) {
                $rules['media_url'] = ['required', 'string'];
            } elseif ($mediaUrl !== '') {
                $rules['media_url'] = ['required', 'url'];
            } else {
                $rules['media_url'] = ['required', 'string'];
            }
        }

        if (! $this->boolean('use_url') && in_array($type, ['image', 'video', 'document', 'audio'], true)) {
            $limits = [
                'image' => 'mimes:jpeg,jpg,png|max:'.(int) (config('templates.header_image_max', 5242880) / 1024),
                'video' => 'mimes:mp4,3gp|max:'.(int) (config('templates.header_video_max', 16777216) / 1024),
                'document' => 'mimes:pdf|max:'.(int) (config('templates.header_document_max', 10485760) / 1024),
                'audio' => 'mimes:mp3,wav,aac,ogg,m4a|max:'.(int) (config('templates.header_audio_max', 16777216) / 1024),
            ];

            if (isset($limits[$type])) {
                $rules['header_media'] = ['required', 'file', $limits[$type]];
            }
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = (string) $this->input('header_type', 'none');

            if (! in_array($type, ['image', 'video', 'document', 'audio'], true)) {
                return;
            }

            if ($this->boolean('use_url') || $this->hasFile('header_media')) {
                return;
            }

            /** @var Template|null $template */
            $template = $this->route('template');
            $existingPath = $template?->wizardPayload()['header']['media_path'] ?? null;
            $existingUrl = $template?->wizardPayload()['header']['media_url'] ?? null;

            if (! filled($existingPath) && ! filled($existingUrl)) {
                $validator->errors()->add('header_media', 'Please upload a file or provide a URL for the header.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'header_text.required' => 'Header text is required when header type is not None.',
            'header_media.required' => 'Please upload a file for the header.',
            'media_url.required' => 'Please provide a URL for the header media.',
            'media_url.url' => 'Enter a valid URL for the header media.',
        ];
    }
}
