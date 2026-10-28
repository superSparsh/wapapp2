<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Http\Requests;

use App\Support\Validation\CampaignWizardValidation;
use Illuminate\Foundation\Http\FormRequest;

class SaveCampaignWizardStepRequest extends FormRequest
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
        $step = (int) $this->route('step');
        $ignoreCampaignId = (int) ($this->session()->get('campaign_wizard.draft_id') ?? 0);

        return CampaignWizardValidation::rulesForStep(
            $step,
            $ignoreCampaignId > 0 ? $ignoreCampaignId : null,
        );
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return CampaignWizardValidation::messages();
    }
}
