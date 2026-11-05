<?php

declare(strict_types=1);

namespace App\Domains\Admin\Services;

use App\Domains\Alerts\Services\AlertDispatcher;
use App\Models\CustomerReadinessSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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
    public const VERIFIED_CERT_SESSION_KEY = 'customer_readiness.verified_certificate';

    public function __construct(
        private readonly RegistrationCertificateService $registrationCertificate,
    ) {}

    /**
     * @return array{submission: CustomerReadinessSubmission, eligible: bool, result: array<string, mixed>}
     */
    public function process(Request $request): array
    {
        $certificate = $this->requireCertificateExtract($request);
        $input = $this->normalizeInput($request->all());
        // Certificate PDF is source of truth — never trust client-supplied name / number.
        $input['business_name'] = $certificate['business_name'];
        $input['registration_number'] = $certificate['registration_number'];
        $input['docType'] = $certificate['doc_type'];
        $validated = $this->validate($input);

        $payload = $this->buildLabeledPayload($validated);
        $payload['Registration certificate file'] = $certificate['original_name'];
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
        $facebookPageUrl = trim((string) ($validated['facebook_page_url'] ?? ''));

        $hardBlock = (
            $validated['mbm_admin'] !== 'admin'
            || $validated['fb_personal'] !== 'available'
            || $fbPageCreated !== 'yes'
            || $facebookPageUrl === ''
            || in_array($validated['num_plan'], ['existing_cannot_use', 'not_decided'], true)
            || $validated['num_otp'] === 'cannot_receive'
            || $validated['num_type'] === 'virtual'
        );

        $missing = [];
        $mustHave = [
            'docType', 'registration_number', 'facebook_page_url', 'mbm_admin', 'mbm_mfa', 'fb_personal', 'fb_page_created',
            'num_plan', 'num_otp', 'num_type', 'site', 'whatsapp_display_name', 'personal_number',
        ];
        foreach ($mustHave as $key) {
            if (! filled($validated[$key] ?? null)) {
                $missing[] = $key;
            }
        }
        if ($enforceWebsite && ! $websiteOk) {
            $missing[] = 'website_down';
        }

        $eligible = ! $hardBlock && $missing === [];
        $softWarn = ($validated['mbm_mfa'] ?? '') !== 'all_admins';
        $reasons = $this->buildReasons($validated, $fbPageCreated, $facebookPageUrl, $websiteOk, $websiteCheck);

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
            'meta' => array_merge($meta, [
                'registration_certificate_path' => $certificate['stored_path'],
                'registration_certificate_original_name' => $certificate['original_name'],
            ]),
        ]);

        $this->notify($submission, $eligible, $reasons, $certificate);

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
        $gstinRegex = (string) config(
            'customer-readiness.gstin_regex',
            '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/'
        );
        $udyamRegex = (string) config(
            'customer-readiness.udyam_regex',
            '/^UDYAM-[A-Z]{2}-[0-9]{2}-[0-9]{7}$/'
        );
        $uamRegex = (string) config(
            'customer-readiness.uam_regex',
            '/^[A-Z]{2}[0-9]{2}[A-Z][0-9]{7}$/'
        );

        $validator = Validator::make($input, [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'personal_number' => ['required', 'regex:/^\+?[0-9\s\-\(\)]{8,20}$/'],
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
            'docType' => ['nullable', 'in:'.implode(',', $docTypes)],
            'registration_number' => [
                'required',
                'string',
                'max:40',
                function (string $attribute, mixed $value, \Closure $fail) use ($gstinRegex, $udyamRegex, $uamRegex): void {
                    $number = strtoupper(preg_replace('/\s+/', '', (string) $value) ?? '');
                    $ok = preg_match($gstinRegex, $number) === 1
                        || preg_match($udyamRegex, $number) === 1
                        || preg_match($uamRegex, $number) === 1;
                    if (! $ok) {
                        $fail('Enter a valid GSTIN, Udyam (UDYAM-XX-00-0000000), or Udyog Aadhaar MSME number.');
                    }
                },
            ],
            'facebook_page_url' => [
                'required',
                'url',
                'max:512',
                'regex:/^https:\/\/(www\.)?facebook\.com\//i',
            ],
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

        $validated = $validator->validated();
        $validated['registration_number'] = strtoupper(preg_replace('/\s+/', '', (string) $validated['registration_number']) ?? '');
        $validated['docType'] = $this->detectDocTypeFromRegistration(
            (string) $validated['registration_number'],
            isset($validated['docType']) ? (string) $validated['docType'] : null,
        );
        $validated['business_name'] = trim((string) ($validated['business_name'] ?? ''));

        $fbCheck = $this->validateFacebookPage((string) $validated['facebook_page_url'], strict: true);
        if (! ($fbCheck['valid'] ?? false)) {
            throw ValidationException::withMessages([
                'facebook_page_url' => [$fbCheck['message'] ?? 'We could not verify this Facebook Page URL.'],
            ]);
        }
        if (! empty($fbCheck['checked_url'])) {
            $validated['facebook_page_url'] = (string) $fbCheck['checked_url'];
        }

        return $validated;
    }

    /**
     * Extract GSTIN/Udyam + business name from an uploaded certificate PDF.
     * On success, caches the verified certificate in session so a later form
     * submit can succeed even if the browser cleared the file input (e.g. after
     * validation errors on other fields).
     *
     * @return array{valid: bool, message: string, business_name: ?string, registration_number: ?string, doc_type: ?string, document_type: ?string}
     */
    public function extractRegistrationCertificate(UploadedFile $file): array
    {
        $extracted = $this->registrationCertificate->extractFromUpload($file);

        if ($extracted['valid'] ?? false) {
            $storedPath = $file->store('customer-readiness/regdocs', 'local');
            session([self::VERIFIED_CERT_SESSION_KEY => [
                'business_name' => (string) $extracted['business_name'],
                'registration_number' => (string) $extracted['registration_number'],
                'doc_type' => (string) $extracted['doc_type'],
                'document_type' => (string) ($extracted['document_type'] ?? ''),
                'original_name' => (string) $file->getClientOriginalName(),
                'stored_path' => $storedPath,
            ]]);
        } else {
            session()->forget(self::VERIFIED_CERT_SESSION_KEY);
        }

        return $extracted;
    }

    /**
     * @return array{
     *     business_name: string,
     *     registration_number: string,
     *     doc_type: string,
     *     original_name: string,
     *     stored_path: string
     * }
     */
    private function requireCertificateExtract(Request $request): array
    {
        $file = $request->file('regdoc_file');
        if ($file instanceof UploadedFile) {
            $extracted = $this->registrationCertificate->extractFromUpload($file);
            if (! ($extracted['valid'] ?? false)) {
                $request->session()->forget(self::VERIFIED_CERT_SESSION_KEY);
                $this->forgetGstOldInput($request);

                throw ValidationException::withMessages([
                    'regdoc_file' => [$extracted['message'] ?? 'Invalid registration certificate.'],
                    'registration_number' => ['GST / MSME number could not be read from the uploaded certificate.'],
                    'business_name' => ['Business name could not be read from the uploaded certificate.'],
                ]);
            }

            $storedPath = $file->store('customer-readiness/regdocs', 'local');
            $payload = [
                'business_name' => (string) $extracted['business_name'],
                'registration_number' => (string) $extracted['registration_number'],
                'doc_type' => (string) $extracted['doc_type'],
                'original_name' => (string) $file->getClientOriginalName(),
                'stored_path' => $storedPath,
            ];
            $request->session()->put(self::VERIFIED_CERT_SESSION_KEY, $payload);

            return $payload;
        }

        // Browser cannot restore <input type="file"> after a redirect. Reuse the
        // certificate verified earlier in this session (extract endpoint / prior attempt).
        $cached = $request->session()->get(self::VERIFIED_CERT_SESSION_KEY);
        if (
            is_array($cached)
            && filled($cached['registration_number'] ?? null)
            && filled($cached['business_name'] ?? null)
            && filled($cached['stored_path'] ?? null)
            && filled($cached['doc_type'] ?? null)
        ) {
            return [
                'business_name' => (string) $cached['business_name'],
                'registration_number' => (string) $cached['registration_number'],
                'doc_type' => (string) $cached['doc_type'],
                'original_name' => (string) ($cached['original_name'] ?? 'certificate.pdf'),
                'stored_path' => (string) $cached['stored_path'],
            ];
        }

        $this->forgetGstOldInput($request);

        throw ValidationException::withMessages([
            'regdoc_file' => ['Upload your GST or Udyam MSME certificate PDF. We can’t onboard you without it.'],
            'registration_number' => ['GST / MSME number could not be read from the uploaded certificate.'],
            'business_name' => ['Business name could not be read from the uploaded certificate.'],
        ]);
    }

    /**
     * On GST/certificate failures, do not flash stale GST No. / Business name back.
     */
    private function forgetGstOldInput(Request $request): void
    {
        $request->merge([
            'registration_number' => null,
            'business_name' => null,
            'docType' => null,
        ]);
    }

    /**
     * @return array{valid: bool, message: string, checked_url?: string, auto_verified?: bool}
     */
    public function validateFacebookPage(string $pageName, bool $strict = false): array
    {
        $raw = trim($pageName);
        $normalizedUrl = $this->normalizeFacebookPageUrl($raw);

        if ($normalizedUrl === null) {
            return [
                'valid' => false,
                'message' => 'Please enter a full public Facebook Page URL starting with https://www.facebook.com/...',
                'auto_verified' => false,
            ];
        }

        $pageId = $this->extractFacebookPageIdentifier($normalizedUrl);
        if ($pageId === null) {
            return [
                'valid' => false,
                'message' => 'That does not look like a Facebook Page URL. Open your Page and paste the public link (for example https://www.facebook.com/YourPageName).',
                'auto_verified' => false,
            ];
        }

        $existence = $this->probeFacebookPageViaGraph($pageId);
        if ($existence === true) {
            return [
                'valid' => true,
                'message' => 'Facebook Page verified.',
                'checked_url' => $normalizedUrl,
                'auto_verified' => true,
            ];
        }

        if ($existence === false) {
            return [
                'valid' => false,
                'message' => 'This Facebook Page is not available (missing, private, or deleted). Paste a public Page URL — we can’t onboard you with an unreachable page.',
                'checked_url' => $normalizedUrl,
                'auto_verified' => false,
            ];
        }

        // Graph probe inconclusive (network / rate-limit). Fall back to HTML scrape signals.
        $htmlProbe = $this->probeFacebookPageViaHttp($normalizedUrl);
        if ($htmlProbe === true) {
            return [
                'valid' => true,
                'message' => 'Facebook Page looks reachable.',
                'checked_url' => $normalizedUrl,
                'auto_verified' => true,
            ];
        }
        if ($htmlProbe === false) {
            return [
                'valid' => false,
                'message' => 'This Facebook Page is not available (missing, private, or deleted). Paste a public Page URL — we can’t onboard you with an unreachable page.',
                'checked_url' => $normalizedUrl,
                'auto_verified' => false,
            ];
        }

        if ($strict) {
            return [
                'valid' => false,
                'message' => 'We could not verify this Facebook Page right now. Please re-check the public Page URL and try again.',
                'auto_verified' => false,
            ];
        }

        return [
            'valid' => true,
            'message' => 'Could not auto-verify right now. We will proceed with your provided page URL.',
            'auto_verified' => false,
            'checked_url' => $normalizedUrl,
        ];
    }

    private function normalizeFacebookPageUrl(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (! filter_var($raw, FILTER_VALIDATE_URL)) {
            $slug = (string) preg_replace('/[^a-zA-Z0-9._-]/', '', str_replace(' ', '', $raw));
            if ($slug === '') {
                return null;
            }
            $raw = 'https://www.facebook.com/'.$slug;
        }

        if (! preg_match('/^https:\/\/(www\.|m\.|web\.)?facebook\.com\//i', $raw)) {
            return null;
        }

        $parts = parse_url($raw);
        if (! is_array($parts) || empty($parts['host']) || empty($parts['path'])) {
            return null;
        }

        $host = strtolower((string) $parts['host']);
        $path = (string) $parts['path'];
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return 'https://www.facebook.com'.$path.$query;
    }

    private function extractFacebookPageIdentifier(string $url): ?string
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return null;
        }

        $query = [];
        if (! empty($parts['query'])) {
            parse_str((string) $parts['query'], $query);
        }

        if (! empty($query['id']) && preg_match('/^\d{5,}$/', (string) $query['id']) === 1) {
            return (string) $query['id'];
        }

        $path = trim((string) ($parts['path'] ?? ''), '/');
        if ($path === '') {
            return null;
        }

        $segments = array_values(array_filter(explode('/', $path), static fn ($s) => $s !== ''));
        if ($segments === []) {
            return null;
        }

        $blocked = [
            'watch', 'reel', 'reels', 'stories', 'story', 'marketplace', 'gaming',
            'groups', 'events', 'friends', 'messages', 'settings', 'login', 'share',
            'permalink.php', 'photo.php', 'photos', 'video.php', 'hashtag',
        ];
        $first = strtolower($segments[0]);
        if (in_array($first, $blocked, true)) {
            return null;
        }

        // /pages/category/Name/123456 or /pages/Name/123456
        if ($first === 'pages') {
            $last = end($segments);
            if (is_string($last) && preg_match('/^\d{5,}$/', $last) === 1) {
                return $last;
            }
            foreach (array_reverse($segments) as $seg) {
                if (! in_array(strtolower($seg), ['pages', 'category'], true) && preg_match('/^[A-Za-z0-9.]+$/', $seg) === 1) {
                    return $seg;
                }
            }

            return null;
        }

        if ($first === 'profile.php') {
            return ! empty($query['id']) ? (string) $query['id'] : null;
        }

        // People sometimes paste /YourPage/about — use first segment.
        if (preg_match('/^[A-Za-z0-9.]+$/', $segments[0]) === 1) {
            return $segments[0];
        }

        return null;
    }

    /**
     * Graph API without app token:
     * - real vanity pages usually return OAuth #200 "Provide valid app ID"
     * - missing pages return GraphMethodException #100 "... does not exist ..."
     *
     * @return bool|null true=exists, false=missing, null=inconclusive
     */
    private function probeFacebookPageViaGraph(string $pageId): ?bool
    {
        try {
            $resp = Http::withHeaders([
                'Accept' => 'application/json',
                'User-Agent' => 'WAPAPP-CustomerReadiness/1.0',
            ])->connectTimeout(4)->timeout(8)->retry(1, 150)->get('https://graph.facebook.com/'.$pageId);

            $json = $resp->json();
            if (is_array($json) && isset($json['id'])) {
                return true;
            }

            $message = strtolower((string) data_get($json, 'error.message', ''));
            $code = (int) data_get($json, 'error.code', 0);
            $type = strtolower((string) data_get($json, 'error.type', ''));

            if (str_contains($message, 'does not exist')) {
                return false;
            }

            // Exists but needs app token / permissions.
            if (
                $type === 'oauthexception'
                || in_array($code, [200, 104, 190], true)
                || str_contains($message, 'provide valid app id')
                || str_contains($message, 'access token is required')
                || str_contains($message, 'requires an access token')
            ) {
                return true;
            }

            // Explicit unsupported/missing.
            if ($code === 100 || $type === 'graphmethodexception') {
                return false;
            }
        } catch (\Throwable $e) {
            Log::notice('Facebook Graph page probe failed', [
                'page_id' => $pageId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * @return bool|null true=looks real, false=unavailable, null=inconclusive
     */
    private function probeFacebookPageViaHttp(string $url): ?bool
    {
        try {
            $resp = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml',
                'Accept-Language' => 'en-US,en;q=0.9',
            ])->connectTimeout(4)->timeout(8)->retry(1, 150)->get($url);

            $status = $resp->status();
            $body = strtolower((string) $resp->body());

            $unavailable = str_contains($body, "this content isn't available")
                || str_contains($body, 'content is not available')
                || str_contains($body, "content isn't available at the moment")
                || str_contains($body, "page isn't available")
                || str_contains($body, 'page not found')
                || str_contains($body, 'sorry, this page')
                || str_contains($body, 'small group of people')
                || str_contains($body, 'changed who can see it')
                || str_contains($body, 'been deleted');

            if ($unavailable || $status === 404) {
                return false;
            }

            $positive = str_contains($body, 'property="og:title"')
                || str_contains($body, '"pageid"')
                || str_contains($body, '"page_id"')
                || preg_match('/"is_page"\s*:\s*true/', $body) === 1;

            if ($status >= 200 && $status < 400 && $positive) {
                return true;
            }

            // Facebook often returns 400/login walls to bots — treat as inconclusive.
            return null;
        } catch (\Throwable) {
            return null;
        }
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

        if (isset($input['registration_number'])) {
            $input['registration_number'] = strtoupper(preg_replace('/\s+/', '', (string) $input['registration_number']) ?? '');
            if (! filled($input['docType'] ?? null)) {
                $input['docType'] = $this->detectDocTypeFromRegistration((string) $input['registration_number']);
            }
        }

        if (! isset($input['facebook_page_url']) && isset($input['fb_page_url'])) {
            $input['facebook_page_url'] = $input['fb_page_url'];
        }

        if (! isset($input['personal_number']) && isset($input['phone'])) {
            $input['personal_number'] = $input['phone'];
        }

        return $input;
    }

    private function detectDocTypeFromRegistration(string $registrationNumber, ?string $hint = null): string
    {
        $number = strtoupper(preg_replace('/\s+/', '', $registrationNumber) ?? '');
        $hint = strtolower(trim((string) $hint));
        $uamRegex = (string) config('customer-readiness.uam_regex', '/^[A-Z]{2}[0-9]{2}[A-Z][0-9]{7}$/');

        if (
            str_contains($hint, 'udyam')
            || str_contains($hint, 'msme')
            || str_contains($hint, 'udyog')
            || str_starts_with($number, 'UDYAM')
            || preg_match($uamRegex, $number) === 1
        ) {
            return 'Udyam MSME certificate';
        }

        return 'GST certificate';
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
            'personal_number' => 'Personal Number',
            'business_name' => 'Business Name',
            'business_email' => 'Business Email',
            'site' => 'Website URL',
            'whatsapp_display_name' => 'WhatsApp Display Name',
            'docType' => 'Registration document',
            'registration_number' => 'GST / MSME Number',
            'facebook_page_url' => 'Facebook Page URL',
            'mbm_admin' => 'Meta Business account admin access',
            'mbm_mfa' => 'Multi-factor authentication on Meta admin',
            'fb_personal' => 'Personal Facebook account available for initial access',
            'fb_page_created' => 'Meta Business account created',
            'fb_page_name' => 'Meta Business portfolio URL',
            'num_plan' => 'Phone Number Plan',
            'num_otp' => 'OTP reachability',
            'num_type' => 'Type of Number',
            'number' => 'WhatsApp Phone Number',
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
    /**
     * Probe a public HTTPS website.
     *
     * Many business sites sit behind Cloudflare / WAF and return 401/403 to bots
     * while remaining fine in a real browser (and for Meta BV). Treat those as
     * reachable. Only DNS / connection failures count as unreachable.
     *
     * @return array{url: string, method: ?string, status: ?int, error: ?string, ok: bool, note?: string}
     */
    public function checkWebsite(string $websiteUrl): array
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

        if (! str_starts_with(strtolower($websiteUrl), 'https://')) {
            $websiteCheck['ok'] = false;
            $websiteCheck['error'] = 'Only HTTPS websites are accepted.';

            return $websiteCheck;
        }

        $client = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
        ])->connectTimeout(5)->timeout(12);

        try {
            $websiteCheck['method'] = 'HEAD';
            $resp = $client->head($websiteUrl);
            $status = $resp->status();

            // Some hosts reject HEAD — fall back to GET.
            if (in_array($status, [403, 405, 501], true)) {
                $websiteCheck['method'] = 'GET';
                $resp = $client->get($websiteUrl);
                $status = $resp->status();
            }

            $websiteCheck['status'] = $status;
            $websiteCheck['ok'] = $this->isWebsiteStatusReachable($status);
            if ($websiteCheck['ok'] && $status >= 400) {
                $websiteCheck['note'] = 'Host responded (bot / access protection).';
            }
        } catch (\Illuminate\Http\Client\RequestException $e) {
            $status = $e->response?->status();
            $websiteCheck['status'] = $status;
            $websiteCheck['error'] = $e->getMessage();

            if ($status !== null && $this->isWebsiteStatusReachable($status)) {
                $websiteCheck['ok'] = true;
                $websiteCheck['note'] = 'Host responded (bot / access protection).';
            } elseif ($this->hostResolves((string) $host)) {
                // Proxy / WAF may block datacenter IPs while browsers still open the site.
                $websiteCheck['ok'] = true;
                $websiteCheck['note'] = 'DNS resolves; HTTP probe blocked (likely WAF). Treated as reachable.';
            } else {
                $websiteCheck['ok'] = false;
            }

            if (! $websiteCheck['ok']) {
                Log::notice('Customer readiness website check failed', [
                    'site' => $websiteUrl,
                    'error' => $e->getMessage(),
                    'status' => $status,
                ]);
            }
        } catch (\Throwable $e) {
            $websiteCheck['error'] = $e->getMessage();
            if ($this->hostResolves((string) $host)) {
                $websiteCheck['ok'] = true;
                $websiteCheck['note'] = 'DNS resolves; HTTP probe failed. Treated as reachable.';
            } else {
                $websiteCheck['ok'] = false;
                Log::notice('Customer readiness website check failed', [
                    'site' => $websiteUrl,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $websiteCheck;
    }

    private function isWebsiteStatusReachable(int $status): bool
    {
        if ($status >= 200 && $status < 400) {
            return true;
        }

        // Bot protection / method rejection — site is up, just not open to scrapers.
        return in_array($status, [401, 403, 405, 406, 429, 503], true);
    }

    private function hostResolves(string $host): bool
    {
        if ($host === '') {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return true;
        }

        $ip = gethostbyname($host);

        return is_string($ip) && $ip !== '' && $ip !== $host;
    }

    /**
     * @return array{valid: bool, message: string, status?: int|null, note?: string}
     */
    public function validateWebsite(string $websiteUrl): array
    {
        $check = $this->checkWebsite(trim($websiteUrl));
        if ($check['ok'] ?? false) {
            $msg = 'Website appears reachable.';
            if (! empty($check['note'])) {
                $msg = 'Website looks live. '.$check['note'];
            }

            return [
                'valid' => true,
                'message' => $msg,
                'status' => $check['status'] ?? null,
                'note' => $check['note'] ?? null,
            ];
        }

        return [
            'valid' => false,
            'message' => 'Website appears unreachable. Meta needs a live HTTPS site for Business Verification.',
            'status' => $check['status'] ?? null,
            'note' => $check['error'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $websiteCheck
     * @return list<string>
     */
    private function buildReasons(
        array $validated,
        string $fbPageCreated,
        string $facebookPageUrl,
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
        if ($fbPageCreated !== 'yes') {
            $reasons[] = 'A Meta Business account must exist before WhatsApp onboarding can proceed.';
        }
        if ($facebookPageUrl === '') {
            $reasons[] = 'A verified public Facebook Page URL is required.';
        }
        if (blank($validated['registration_number'] ?? null)) {
            $reasons[] = 'A verified GSTIN or Udyam MSME number is required.';
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
                $msg .= ' - '.$websiteCheck['error'];
            }
            $msg .= '. URL checked: '.($websiteCheck['url'] ?? '');
            $reasons[] = $msg;
        }

        return $reasons;
    }

    /**
     * @param  list<string>  $reasons
     * @param  array{business_name: string, registration_number: string, doc_type: string, original_name: string, stored_path: string}  $certificate
     */
    private function notify(
        CustomerReadinessSubmission $submission,
        bool $eligible,
        array $reasons,
        array $certificate,
    ): void {
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
            'certificate_path' => $certificate['stored_path'],
            'certificate_original_name' => $certificate['original_name'],
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
