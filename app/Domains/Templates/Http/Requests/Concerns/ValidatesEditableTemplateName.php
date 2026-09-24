<?php

declare(strict_types=1);

namespace App\Domains\Templates\Http\Requests\Concerns;

use App\Domains\Templates\Support\TemplateNameValidator;
use App\Models\Template;

trait ValidatesEditableTemplateName
{
    /**
     * @return array<string, list<string>>
     */
    protected function editableNameRules(): array
    {
        $template = $this->route('template');

        if (! $template instanceof Template || ! $template->canEditIdentity()) {
            return [];
        }

        return [
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_]+$/'],
        ];
    }

    protected function validateEditableNameUnique($validator): void
    {
        $template = $this->route('template');

        if (! $template instanceof Template || ! $template->canEditIdentity()) {
            return;
        }

        $name = (string) $this->input('name', '');

        if ($name === '') {
            return;
        }

        if (TemplateNameValidator::nameExistsForLine($name, $template->whatsapp_line_id, $template->id)) {
            $validator->errors()->add(
                'name',
                'A template with this name already exists on this WhatsApp number. Please choose a different name.',
            );
        }
    }

    /**
     * @return array<string, string>
     */
    protected function editableNameMessages(): array
    {
        return [
            'name.regex' => 'Template name must use lowercase letters, numbers, and underscores only.',
            'name.required' => 'Template name is required.',
        ];
    }
}
