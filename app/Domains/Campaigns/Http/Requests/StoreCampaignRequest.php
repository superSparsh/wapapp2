<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Http\Requests;

use App\Support\Validation\CampaignWizardValidation;
use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignRequest extends FormRequest
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
        return CampaignWizardValidation::storeRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(CampaignWizardValidation::messages(), [
            'audience_id.required' => 'Please select an audience list before sending.',
            'whatsapp_line_id.required' => 'Please choose a From Number before sending.',
            'template_id.required' => 'Please select a template before sending.',
        ]);
    }
}
