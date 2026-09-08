<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Http\Requests\GoogleCalendar;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGoogleCalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'settings.whatsapp_number'       => ['nullable', 'string', 'regex:/^\+[1-9]\d{7,14}$/'],
            'settings.user_email'            => ['nullable', 'email', 'max:255'],
            'settings.enable_whatsapp'       => ['nullable', 'in:on'],
            'settings.calendar_id'           => ['nullable', 'string', 'max:255'],
            'settings.meet_only'             => ['nullable', 'in:on'],
            'settings.reminder_minutes_before' => ['nullable', 'integer', 'in:15,30,45,60,90,120,180,240,360,720,1440'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'settings.whatsapp_number.regex'          => 'WhatsApp number must be in international format (e.g. +911234567890).',
            'settings.reminder_minutes_before.in'     => 'Please select a valid reminder interval.',
        ];
    }
}
