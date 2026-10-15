<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Http\Requests\GoogleCalendar;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBookingAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $weekly = $this->input('booking_availability.weekly', []);
        if (! is_array($weekly)) {
            return;
        }

        foreach ($weekly as $day => $data) {
            if (! is_array($data)) {
                continue;
            }

            $weekly[$day]['enabled'] = ! empty($data['enabled'])
                && $data['enabled'] !== '0'
                && $data['enabled'] !== 'false';
        }

        $this->merge([
            'booking_availability' => ['weekly' => $weekly],
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'booking_availability'                             => ['nullable', 'array'],
            'booking_availability.weekly'                     => ['nullable', 'array'],
            'booking_availability.weekly.*.enabled'           => ['nullable', 'boolean'],
            'booking_availability.weekly.*.intervals'         => ['nullable', 'array'],
            'booking_availability.weekly.*.intervals.*.start' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'booking_availability.weekly.*.intervals.*.end'   => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/'],
        ];
    }
}
