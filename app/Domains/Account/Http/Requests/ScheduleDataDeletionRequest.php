<?php

declare(strict_types=1);

namespace App\Domains\Account\Http\Requests;

use App\Support\DataDeletionConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScheduleDataDeletionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('web') !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'data_age' => ['required', Rule::in(array_keys(DataDeletionConfig::dataAgeLabels()))],
            'modules' => ['required', 'array', 'min:1'],
            'modules.*' => [Rule::in(array_keys(DataDeletionConfig::modules()))],
            'schedule_delay' => ['required', Rule::in(array_keys(DataDeletionConfig::scheduleLabels()))],
            'export_before_delete' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'data_age.required' => 'Please select a data age.',
            'data_age.in' => 'Please choose a valid data age.',
            'modules.required' => 'Please select at least one module.',
            'modules.min' => 'Please select at least one module.',
            'modules.*.in' => 'One or more selected modules are invalid.',
            'schedule_delay.required' => 'Please choose when to delete the data.',
            'schedule_delay.in' => 'Please choose a valid deletion schedule.',
        ];
    }
}
