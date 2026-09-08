<?php

declare(strict_types=1);

namespace App\Domains\Drip\Support;

use App\Models\MailList;
use App\Support\PublicId;

final class DripCampaignValidation
{
    /**
     * @return array<string, mixed>
     */
    public static function createRules(string $triggerType): array
    {
        return array_merge([
            'name' => ['required', 'string', 'min:2', 'max:191'],
            'audience_id' => PublicId::uuidExistsRules(MailList::class),
            'timezone' => ['nullable', 'timezone:all'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ], DripTriggerCatalog::validationRules($triggerType));
    }

    /**
     * @return array<string, mixed>
     */
    public static function settingsRules(string $triggerType): array
    {
        return array_merge([
            'name' => ['required', 'string', 'min:2', 'max:191'],
            'audience_id' => PublicId::uuidExistsRules(MailList::class, nullable: false),
            'timezone' => ['required', 'timezone:all'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ], DripTriggerCatalog::validationRules($triggerType));
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'name.required' => 'Automation name is required.',
            'name.min' => 'Automation name must be at least 2 characters.',
            'audience_id.required' => 'Please select an audience list.',
            'audience_id.exists' => 'The selected audience is invalid.',
            'audience_id.uuid' => 'Please select an audience list.',
            'timezone.required' => 'Please select a time zone.',
            'timezone.timezone' => 'Please select a valid time zone.',
            'start_date.required' => 'Start date is required.',
            'end_date.required' => 'End date is required.',
            'end_date.after_or_equal' => 'End date must be on or after the start date.',
            'trigger_type.required' => 'Please select a trigger.',
            'trigger_options.date.required' => 'Trigger date is required.',
            'trigger_options.at.required' => 'Trigger time is required.',
            'trigger_options.field.required' => 'Date field is required.',
            'trigger_options.tag_name.required' => 'Tag name is required.',
            'trigger_options.days_of_week.required' => 'Select at least one day of the week.',
            'trigger_options.days_of_week.min' => 'Select at least one day of the week.',
            'trigger_options.days_of_month.required' => 'Select at least one day of the month.',
            'trigger_options.days_of_month.min' => 'Select at least one day of the month.',
            'trigger_options.woo_source.required' => 'WooCommerce store is required for abandoned cart triggers.',
        ];
    }
}
