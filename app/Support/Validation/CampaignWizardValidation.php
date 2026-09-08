<?php

declare(strict_types=1);

namespace App\Support\Validation;

use App\Domains\Templates\Enums\TemplateStatus;
use App\Models\MailList;
use App\Models\Template;
use App\Models\WhatsappLine;
use App\Support\PublicId;
use Illuminate\Validation\Rule;

final class CampaignWizardValidation
{
    /**
     * @return array<string, mixed>
     */
    public static function rulesForStep(int $step): array
    {
        return match ($step) {
            1 => [
                'name' => ['required', 'string', 'min:2', 'max:255'],
                'whatsapp_line_id' => PublicId::uuidExistsRules(WhatsappLine::class, nullable: false),
            ],
            2 => [
                'audience_id' => PublicId::uuidExistsRules(MailList::class, nullable: false),
            ],
            3 => [
                'template_id' => array_merge(
                    PublicId::uuidExistsRules(Template::class, nullable: false),
                    [self::approvedTemplateRule()],
                ),
            ],
            4 => [
                'recipients' => ['nullable', 'array'],
                'recipients.*.values' => ['nullable', 'array'],
                'recipients.*.values.*' => ['nullable', 'string', 'max:60'],
            ],
            5 => [],
            6 => [
                'send_mode' => ['required', 'in:now,schedule'],
                'scheduled_at' => ['required_if:send_mode,schedule', 'nullable', 'date', 'after:now'],
            ],
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'name.required' => 'Campaign name is required.',
            'name.min' => 'Campaign name must be at least 2 characters.',
            'whatsapp_line_id.required' => 'Please choose a From Number.',
            'whatsapp_line_id.exists' => 'The selected From Number is invalid.',
            'whatsapp_line_id.uuid' => 'Please choose a From Number.',
            'audience_id.required' => 'Please select an audience list.',
            'audience_id.exists' => 'The selected audience is invalid.',
            'audience_id.uuid' => 'Please select an audience list.',
            'template_id.required' => 'Please select a template.',
            'template_id.exists' => 'The selected template is invalid.',
            'template_id.uuid' => 'Please select a template.',
            'template_variables.*.max' => 'Each variable value may not be greater than 60 characters.',
            'send_mode.required' => 'Please choose when to send the campaign.',
            'scheduled_at.required_if' => 'Scheduled date and time are required.',
            'scheduled_at.after' => 'Scheduled time must be in the future.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function storeRules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'audience_id' => PublicId::uuidExistsRules(MailList::class, nullable: false),
            'whatsapp_line_id' => PublicId::uuidExistsRules(WhatsappLine::class, nullable: false),
            'template_id' => array_merge(
                PublicId::uuidExistsRules(Template::class, nullable: false),
                [self::approvedTemplateRule()],
            ),
            'template_variables' => ['nullable', 'array'],
            'template_variables.*' => ['nullable', 'string', 'max:60'],
            'scheduled_at' => ['required_if:send_mode,schedule', 'nullable', 'date', 'after:now'],
            'send_mode' => ['required', 'in:now,schedule'],
        ];
    }

    private static function approvedTemplateRule(): \Illuminate\Validation\Rules\Exists
    {
        return Rule::exists('templates', 'uuid')->where(function ($query): void {
            $query->where('status', TemplateStatus::Approved->value)
                ->whereNotNull('code')
                ->where('code', '!=', '');
        });
    }
}
