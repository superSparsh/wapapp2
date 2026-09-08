<?php

declare(strict_types=1);

namespace App\Domains\Drip\Http\Requests;

use App\Domains\Drip\Support\DripCampaignValidation;
use App\Domains\Drip\Support\DripTriggerCatalog;
use App\Models\MailList;
use App\Support\PublicId;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDripCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('trigger_type')) {
            return;
        }

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
        if (! $this->has('trigger_type')) {
            return [
                'name' => ['sometimes', 'string', 'min:2', 'max:191'],
                'audience_id' => PublicId::uuidExistsRules(MailList::class),
                'timezone' => ['nullable', 'timezone:all'],
                'start_date' => ['nullable', 'date'],
                'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            ];
        }

        $type = DripTriggerCatalog::normalizeType($this->input('trigger_type'));

        return DripCampaignValidation::settingsRules($type);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return DripCampaignValidation::messages();
    }
}
