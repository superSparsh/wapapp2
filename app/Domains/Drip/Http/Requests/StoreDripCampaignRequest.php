<?php

declare(strict_types=1);

namespace App\Domains\Drip\Http\Requests;

use App\Domains\Drip\Support\DripCampaignValidation;
use App\Domains\Drip\Support\DripTriggerCatalog;
use Illuminate\Foundation\Http\FormRequest;

class StoreDripCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $type = DripTriggerCatalog::normalizeType($this->input('trigger_type'));

        $this->merge([
            'trigger_type' => $type,
            'trigger_options' => DripTriggerCatalog::sanitizeOptions(
                $type,
                (array) $this->input('trigger_options', []),
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = DripTriggerCatalog::normalizeType($this->input('trigger_type'));

        return DripCampaignValidation::createRules($type);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return DripCampaignValidation::messages();
    }
}
