<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Http\Requests\GoogleCalendar;

use Illuminate\Foundation\Http\FormRequest;

class CreateMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'summary'          => ['required', 'string', 'max:255'],
            'start_at'         => ['required', 'date', 'after:now'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'attendee_name'    => ['required', 'string', 'max:255'],
            'attendee_phone'   => ['required', 'string', 'regex:/^\+?[1-9]\d{7,14}$/'],
            'attendee_email'   => ['nullable', 'email', 'max:255'],
            'description'      => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'attendee_phone.regex' => 'Please enter a valid WhatsApp number (e.g. +911234567890).',
            'start_at.after'       => 'Meeting start time must be in the future.',
        ];
    }
}
