<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Http\Requests;

use App\Models\Campaign;
use App\Support\Validation\CampaignWizardValidation;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCampaignRequest extends FormRequest
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
        $campaign = $this->route('campaign');
        $ignoreId = $campaign instanceof Campaign ? (int) $campaign->id : null;

        return [
            'name' => CampaignWizardValidation::nameRules(required: false, ignoreCampaignId: $ignoreId),
            'audience_id' => ['nullable', 'integer', 'exists:mail_lists,id'],
            'whatsapp_line_id' => ['nullable', 'integer', 'exists:whatsapp_lines,id'],
            'template_id' => ['nullable', 'integer', 'exists:templates,id'],
            'template_variables' => ['nullable', 'array'],
            'template_variables.*' => ['string'],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A campaign with this name already exists. Please choose a different name.',
        ];
    }
}
