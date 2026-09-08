<?php

declare(strict_types=1);

namespace App\Domains\Audience\Http\Controllers;

use App\Domains\Audience\Enums\ContactStatus;
use App\Domains\Audience\Services\EmbeddedFormService;
use App\Enums\ContactOptInStatus;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\MailList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PublicEmbeddedFormController extends Controller
{
    public function __construct(
        private readonly EmbeddedFormService $embeddedForms,
    ) {}

    public function show(MailList $list): View
    {
        return view('audience.embedded-form-public', [
            'list' => $list,
            'options' => $this->embeddedForms->optionsFor($list),
            'action' => $this->embeddedForms->subscribeUrl($list),
            'preview' => false,
        ]);
    }

    public function preview(MailList $list): View
    {
        return view('audience.embedded-form-public', [
            'list' => $list,
            'options' => $this->embeddedForms->optionsFor($list),
            'action' => '#',
            'preview' => true,
        ]);
    }

    public function subscribe(Request $request, MailList $list): RedirectResponse|Response|View
    {
        $validated = $request->validate([
            'country_code' => ['required', 'string', 'max:20'],
            'phone_number' => ['required', 'string', 'max:50'],
            'FIRST_NAME' => ['nullable', 'string', 'max:200'],
            'LAST_NAME' => ['nullable', 'string', 'max:200'],
            'redirect_url' => ['nullable', 'string', 'max:500'],
        ]);

        $phone = $this->normalizePhone((string) $validated['phone_number']);
        abort_if($phone === '', 422, 'WhatsApp number is required.');

        $firstName = trim((string) ($validated['FIRST_NAME'] ?? ''));
        $lastName = trim((string) ($validated['LAST_NAME'] ?? ''));
        $name = trim($firstName.' '.$lastName);
        $countryCode = trim((string) $validated['country_code']);

        Contact::query()->updateOrCreate(
            [
                'mail_list_id' => $list->id,
                'phone' => $phone,
            ],
            array_filter([
                'name' => $name !== '' ? $name : null,
                'country_code' => $countryCode !== '' ? $countryCode : null,
                'status' => ContactStatus::Subscribed,
                'opt_in_status' => ContactOptInStatus::OptedIn,
                'opted_in_at' => now(),
                'source' => 'embedded_form',
            ], static fn ($value) => $value !== null)
        );

        $redirect = $request->input('redirect_url')
            ?: ($this->embeddedForms->optionsFor($list)['redirect_url'] ?? '');

        if (is_string($redirect) && $redirect !== '') {
            return redirect()->away($redirect);
        }

        return response(
            '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Subscribed</title></head><body style="font-family:system-ui;padding:32px;text-align:center"><h1>You\'re subscribed</h1><p>Thanks for joining our WhatsApp list.</p></body></html>',
            200,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }

    private function normalizePhone(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9+]/', '', $phone) ?? '';
        if ($cleaned !== '' && ! str_starts_with($cleaned, '+')) {
            $cleaned = '+'.$cleaned;
        }

        return $cleaned;
    }
}
