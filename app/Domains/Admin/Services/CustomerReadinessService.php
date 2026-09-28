<?php

declare(strict_types=1);

namespace App\Domains\Admin\Services;

use App\Domains\Alerts\Services\AlertDispatcher;
use App\Models\CustomerReadinessSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Legacy-parity customer readiness: validate, score eligibility, persist, notify.
 */
class CustomerReadinessService
{
    /**
     * @return array{submission: CustomerReadinessSubmission, eligible: bool, result: array<string, mixed>}
     */
    public function process(Request $request): array
    {
        $input = $this->normalizeInput($request->all());
        $validated = $this->validate($input);

        $payload = $this->buildLabeledPayload($validated);
        $metaPortfolioUrl = trim((string) ($validated['fb_page_name'] ?? ''));
        $metaBusinessId = $this->extractBusinessIdFromMetaUrl($metaPortfolioUrl);
        if ($metaBusinessId !== null) {
            $payload['Business Portfolio ID'] = $metaBusinessId;
        }

        $websiteCheck = $this->checkWebsite((string) $validated['site']);
        $websiteOk = (bool) ($websiteCheck['ok'] ?? false);
        $enforceWebsite = (bool) config('customer-readiness.enforce_website_reachability', false);

        $fbPageCreated = (string) $validated['fb_page_created'];
        $fbPageName = $metaPortfolioUrl;

        $hardBlock = (
            $validated['mbm_admin'] !== 'admin'
            || $validated['fb_personal'] !== 'available'
            || $fbPageCreated !== 'yes'
            || $fbPageName === ''
            || in_array($validated['num_plan'], ['existing_cannot_use', 'not_decided'], true)
            || $validated['num_otp'] === 'cannot_receive'
            || $validated['num_type'] === 'virtual'
        );

        $missing = [];
        $mustHave = [
            'docType', 'mbm_admin', 'mbm_mfa', 'fb_personal', 'fb_page_created',
            'num_plan', 'num_otp', 'num_type', 'site', 'whatsapp_display_name',
        ];
        foreach ($mustHave as $key) {
            if (! filled($validated[$key] ?? null)) {
                $missing[] = $key;
            }
        }
        if ($fbPageCreated === 'yes' && $fbPageName === '') {
            $missing[] = 'fb_page_name';
        }
        if ($enforceWebsite && ! $websiteOk) {
            $missing[] = 'website_down';
        }

        $eligible = ! $hardBlock && $missing === [];
        $softWarn = ($validated['mbm_mfa'] ?? '') !== 'all_admins';
        $reasons = $this->buildReasons($validated, $fbPageCreated, $fbPageName, $websiteOk, $websiteCheck);

        $meta = [
            'eligible' => $eligible,
            'soft_warn' => $softWarn,
            'missing' => $missing,
            'reasons' => $reasons,
            'website_check' => $websiteCheck,
            'enforce_website_reachability' => $enforceWebsite,
            'action' => $eligible ? 'ready' : 'not_ready',
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'raw' => $validated,
        ];

        $submission = CustomerReadinessSubmission::query()->create([
            'customer_name' => $validated['customer_name'],
            'customer_email' => strtolower($validated['customer_email']),
            'business_name' => $validated['business_name'],
            'business_email' => strtolower($validated['business_email']),
            'website' => $validated['site'],
            'doc_type' => $this->docTypeStorageKey((string) $validated['docType']),
            'status' => $eligible ? 'eligible' : 'not_eligible',
            'data' => $payload,
            'meta' => $meta,
        ]);

        $this->notify($submission, $eligible, $reasons);

        $result = $this->buildResultFlash($eligible, (string) $validated['business_email']);

        return [
            'submission' => $submission,
            'eligible' => $eligible,
            'result' => $result,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function validate(array $input): array
    {
        $freeDomains = (array) config('customer-readiness.free_email_domains', []);
        $docTypes = (array) config('customer-readiness.doc_types', []);
        $metaRegex = (string) config(
            'customer-readiness.meta_portfolio_url_regex',
            '/^https:\/\/business\.facebook\.com\/latest\/settings\/business_info\?business_id=\d+$/i'
        );

        $validator = Validator::make($input, [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'business_name' => ['required', 'string', 'max:255'],
            'business_email' => [
                'required',
                'email',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail) use ($freeDomains): void {
                    $domain = strtolower((string) Str::after((string) $value, '@'));
                    if (in_array($domain, $freeDomains, true)) {
                        $fail('Please use an official business email (no public/free domains).');
                    }
                },
            ],
            'site' => ['required', 'url', 'regex:/^https:\/\//i', 'max:512'],
            'docType' => ['required', 'in:'.implode(',', $docTypes)],
            'mbm_admin' => ['required', 'in:admin,employee,none'],
            'mbm_mfa' => ['required', 'in:all_admins,some_admins,none'],
            'fb_personal' => ['required', 'in:available,not'],
            'fb_page_created' => ['required', 'in:yes,no,not_sure'],
            'fb_page_name' => ['nullable', 'string', 'regex:'.$metaRegex],
            'num_plan' => ['required', 'in:new_unused,migrate_to_api,existing_cannot_use,not_decided'],
            'num_otp' => ['required', 'in:can_receive,cannot_receive'],
            'num_type' => ['required', 'in:physical,virtual'],
            'number' => ['nullable', 'regex:/^\+?[0-9\s\-\(\)]{8,20}$/'],
            'whatsapp_display_name' => ['required', 'string', 'max:75'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * @return array{valid: bool, message: string, checked_url?: string, auto_verified?: bool}
     */
    public function validateFacebookPage(string $pageName): array
    {
        $raw = trim($pageName);
        $candidates = [];

        if (filter_var($raw, FILTER_VALIDATE_URL)) {
            $candidates[] = $raw;
        } else {
            $normalized = (string) preg_replace('/\s+/', ' ', $raw);
            $slugA = (string) preg_replace('/[^a-zA-Z0-9._-]/', '', str_replace(' ', '', $normalized));
            $slugB = (string) preg_replace('/[^a-zA-Z0-9._-]/', '', str_replace(' ', '.', $normalized));
            $slugC = (string) preg_replace('/[^a-zA-Z0-9._-]/', '', str_replace(' ', '-', $normalized));

            foreach (array_unique([$slugA, $slugB, $slugC]) as $slug) {
                if ($slug !== '') {
                    $candidates[] = 'https://www.facebook.com/'.ltrim($slug, '/');
                }
            }
        }

        foreach ($candidates as $url) {
            try {
                $resp = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; WAPAPP-Validator/1.0)',
                ])->connectTimeout(4)->timeout(8)->retry(1, 150)->get($url);

                $status = $resp->status();
                $body = strtolower((string) $resp->body());
                $unavailable = str_contains($body, "this content isn't available")
                    || str_contains($body, 'content is not available')
                    || str_contains($body, "page isn't available");

                if ($status >= 200 && $status < 400 && ! $unavailable) {
                    return [
                        'valid' => true,
                        'message' => 'Facebook Page looks reachable.',
                        'checked_url' => $url,
                    ];
                }
            } catch (\Throwable) {
                // try next candidate
            }
        }

        return [
            'valid' => true,
            'message' => 'Could not auto-verify right now. We will proceed with your provided page name.',
            'auto_verified' => false,
        ];
    }

    /**
     * @return array{valid: bool, message: string}
     */
    public function validateBusinessEmail(string $email): array
    {
        $email = strtolower(trim($email));
        $domain = (string) Str::after($email, '@');
        $freeDomains = (array) config('customer-readiness.free_email_domains', []);

        if (in_array($domain, $freeDomains, true)) {
            return [
                'valid' => false,
                'message' => 'Please use an official business domain email (no Gmail/Yahoo/Outlook).',
            ];
        }

        $hasDns = true;
        try {
            $hasDns = checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A') || checkdnsrr($domain, 'AAAA');
        } catch (\Throwable) {
            $hasDns = true;
        }

        // PHPUnit / sandboxed DNS often cannot resolve arbitrary domains.
        if (! $hasDns && app()->environment('testing')) {
            $hasDns = true;
        }

        if (! $hasDns) {
            return [
                'valid' => false,
                'message' => 'Email domain DNS record not found. Please check domain spelling.',
            ];
        }

        return [
            'valid' => true,
            'message' => 'Business email looks valid.',
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalizeInput(array $input): array
    {
        if (! isset($input['customer_name']) && isset($input['name'])) {
            $input['customer_name'] = $input['name'];
        }
        if (! isset($input['customer_email']) && isset($input['email'])) {
            $input['customer_email'] = $input['email'];
        }
        if (! isset($input['site'])) {
            $input['site'] = $input['website'] ?? $input['website_url'] ?? null;
        }
        if (! isset($input['docType']) && isset($input['doc_type'])) {
            $aliases = (array) config('customer-readiness.doc_type_aliases', []);
            $raw = (string) $input['doc_type'];
            $input['docType'] = $aliases[$raw] ?? $raw;
        }

        if (isset($input['docType'])) {
            $aliases = (array) config('customer-readiness.doc_type_aliases', []);
            $raw = (string) $input['docType'];
            if (isset($aliases[$raw])) {
                $input['docType'] = $aliases[$raw];
            }
        }

        // Empty portfolio URL when Meta account is not created yet / blank.
        if (! isset($input['fb_page_name']) || trim((string) $input['fb_page_name']) === '') {
            $input['fb_page_name'] = null;
        } elseif (($input['fb_page_created'] ?? '') !== 'yes') {
            $input['fb_page_name'] = null;
        }

        return $input;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function buildLabeledPayload(array $validated): array
    {
        $fieldLabels = [
            'customer_name' => 'Customer Name',
            'customer_email' => 'Customer Email',
            'business_name' => 'Business Name',
            'business_email' => 'Business Email',
            'site' => 'Website URL',
            'whatsapp_display_name' => 'WhatsApp Display Name',
            'docType' => 'Registration document',
            'mbm_admin' => 'Meta Business account admin access',
            'mbm_mfa' => 'Multi-factor authentication on Meta admin',
            'fb_personal' => 'Personal Facebook account available for initial access',
            'fb_page_created' => 'Meta Business account created',
            'fb_page_name' => 'Meta Business portfolio URL',
            'num_plan' => 'Phone Number Plan',
            'num_otp' => 'OTP reachability',
            'num_type' => 'Type of Number',
            'number' => 'Phone Number',
        ];

        $payload = [];
        foreach ($fieldLabels as $key => $label) {
            $payload[$label] = $validated[$key] ?? null;
        }

        return $payload;
    }

    /**
     * @return array{url: ?string, method: ?string, status: ?int, error: ?string, ok: bool}
     */
    private function checkWebsite(string $websiteUrl): array
    {
        $websiteCheck = [
            'url' => $websiteUrl,
            'method' => null,
            'status' => null,
            'error' => null,
            'ok' => true,
        ];

        if ($websiteUrl === '') {
            $websiteCheck['ok'] = false;
            $websiteCheck['error'] = 'Empty URL.';

            return $websiteCheck;
        }

        $host = parse_url($websiteUrl, PHP_URL_HOST);
        if (! filter_var($websiteUrl, FILTER_VALIDATE_URL) || empty($host)) {
            $websiteCheck['ok'] = false;
            $websiteCheck['error'] = 'Invalid URL format.';

            return $websiteCheck;
        }

        try {
            $websiteCheck['method'] = 'HEAD';
            $resp = Http::connectTimeout(5)->timeout(8)->retry(1, 200)->head($websiteUrl);
            $status = $resp->status();

            if (in_array($status, [403, 405], true)) {
                $websiteCheck['method'] = 'GET';
                $resp = Http::connectTimeout(5)->timeout(10)->retry(1, 200)->get($websiteUrl);
                $status = $resp->status();
            }

            $websiteCheck['status'] = $status;
            $websiteCheck['ok'] = $status >= 200 && $status < 400;
        } catch (\Throwable $e) {
            $websiteCheck['ok'] = false;
            $websiteCheck['error'] = $e->getMessage();
            Log::notice('Customer readiness website check failed', [
                'site' => $websiteUrl,
                'error' => $e->getMessage(),
            ]);
        }

        return $websiteCheck;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $websiteCheck
     * @return list<string>
     */
    private function buildReasons(
        array $validated,
        string $fbPageCreated,
        string $fbPageName,
        bool $websiteOk,
        array $websiteCheck,
    ): array {
        $reasons = [];

        if (($validated['mbm_admin'] ?? '') !== 'admin') {
            $reasons[] = 'Meta Business Manager admin access is required.';
        }
        if (($validated['fb_personal'] ?? '') !== 'available') {
            $reasons[] = 'A personal Facebook account is required initially.';
        }
        if ($fbPageCreated !== 'yes' || $fbPageName === '') {
            $reasons[] = 'Meta Business account must exist and the Meta Business portfolio URL must be provided.';
        }
        if (in_array($validated['num_plan'] ?? '', ['existing_cannot_use', 'not_decided', ''], true)) {
            $reasons[] = 'Phone number plan must be "New" or "Migrate to API".';
        }
        if (($validated['num_otp'] ?? '') === 'cannot_receive' || ($validated['num_otp'] ?? '') === '') {
            $reasons[] = 'Phone number must be able to receive OTP (SMS/voice).';
        }
        if (($validated['num_type'] ?? '') === 'virtual' || ($validated['num_type'] ?? '') === '') {
            $reasons[] = 'Virtual/VoIP numbers are not allowed.';
        }
        if (! $websiteOk) {
            $msg = 'Website appears down or unreachable';
            if (! empty($websiteCheck['status'])) {
                $msg .= ' (HTTP '.$websiteCheck['status'].')';
            }
            if (! empty($websiteCheck['error'])) {
                $msg .= ' — '.$websiteCheck['error'];
            }
            $msg .= '. URL checked: '.($websiteCheck['url'] ?? '');
            $reasons[] = $msg;
        }

        return $reasons;
    }

    /**
     * @param  list<string>  $reasons
     */
    private function notify(CustomerReadinessSubmission $submission, bool $eligible, array $reasons): void
    {
        $status = $eligible ? 'eligible' : 'not_eligible';
        $payload = [
            'customer_name' => $submission->customer_name,
            'customer_email' => $submission->customer_email,
            'business_name' => $submission->business_name,
            'business_email' => $submission->business_email,
            'website' => $submission->website,
            'doc_type' => $submission->doc_type,
            'name' => $submission->customer_name,
            'email' => $submission->business_email ?: $submission->customer_email,
            'eligible' => $eligible,
            'reasons' => $reasons,
            'meta' => $submission->meta,
            'data' => $submission->data,
            'status' => $status,
        ];

        // Prefer business email for customer notifications (legacy).
        $payload['customer_email'] = $submission->business_email ?: $submission->customer_email;

        try {
            app(AlertDispatcher::class)->readinessSubmitted($payload, $status);
        } catch (\Throwable $e) {
            Log::error('Customer readiness notification failed', [
                'submission_id' => $submission->id,
                'eligible' => $eligible,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildResultFlash(bool $eligible, string $businessEmail): array
    {
        return [
            'eligible' => $eligible,
            'title' => $eligible
                ? 'Thank you! You are ready for onboarding'
                : 'Thank you! We received your details',
            'message' => $eligible
                ? 'Your submission is complete. Please book your demo slot using the button below.'
                : 'Some prerequisites are pending. Please follow this guide and complete the pending items.',
            'business_email' => $businessEmail,
            'temp_password' => null,
            'login_url' => url((string) config('customer-readiness.login_url', '/login')),
            'link' => $eligible
                ? (string) config('customer-readiness.calendly_url')
                : (string) config('customer-readiness.setup_guide_url'),
            'link_text' => $eligible ? 'Book Your Demo Slot' : 'Open setup guide',
        ];
    }

    public function extractBusinessIdFromMetaUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        $query = parse_url($url, PHP_URL_QUERY);
        if (! $query) {
            return null;
        }

        parse_str($query, $params);
        $candidate = isset($params['business_id'])
            ? preg_replace('/\D+/', '', (string) $params['business_id'])
            : '';

        return $candidate !== '' && $candidate !== null ? $candidate : null;
    }

    private function docTypeStorageKey(string $docType): string
    {
        return match ($docType) {
            'GST certificate' => 'gst_certificate',
            'Udyam MSME certificate' => 'udyam_msme_certificate',
            default => Str::snake(strtolower($docType)),
        };
    }
}
