<?php

declare(strict_types=1);

namespace App\Domains\Forms\Services;

use App\Domains\Admin\Services\AdminNotificationService;
use App\Domains\Billing\Services\BillingAddressService;
use App\Domains\Forms\Mail\FormsOnboardingCredentialsMail;
use App\Domains\Tenancy\Services\TenantDatabaseNamingService;
use App\Domains\Tenancy\Services\TenantSlugService;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Enums\TenantUserAccountType;
use App\Enums\UserRole;
use App\Models\CustomerOnboardingSubmission;
use App\Models\CustomerReadinessSubmission;
use App\Models\IsvTermsAcceptance;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantUserAccess;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Legacy-parity forms onboarding: create/reuse tenant + owner, assign Ginger plan (1 year),
 * store readiness/onboarding rows, signature PDF, credentials mail.
 */
class FormsOnboardingService
{
    public function __construct(
        private readonly FormsPlanResolver $planResolver,
        private readonly RegistrationPdfExtractor $pdfExtractor,
        private readonly FormsSignatureDocumentService $signatureDocuments,
        private readonly TenantSlugService $tenantSlugService,
        private readonly TenantDatabaseNamingService $databaseNamingService,
        private readonly BillingAddressService $billingAddressService,
    ) {}

    /**
     * @return array{
     *     submission_id: int,
     *     user_id: int,
     *     customer_id: string,
     *     login_email: string,
     *     temp_password: string,
     *     login_url: string,
     *     is_new_user: bool,
     *     signature_pdf_generated: bool
     * }
     */
    public function process(Request $request): array
    {
        $this->mergePayloadIntoRequest($request);

        $validated = Validator::make($request->all(), [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email'],
            'business_name' => ['required', 'string', 'max:255'],
            'business_email' => ['required', 'email'],
            'docType' => ['nullable', 'in:GST certificate,Udyam MSME certificate,PAN Card'],
            'regdoc_file' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'number' => ['required', 'regex:/^\+?[0-9\s\-\(\)]{8,20}$/'],
            'whatsapp_display_name' => ['required', 'string', 'max:75'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'gstin' => ['nullable', 'string', 'max:20'],
            'addr1' => ['nullable', 'string', 'max:255'],
            'addr2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'pin' => ['nullable', 'string', 'max:20'],
            'pan' => ['required', 'string', 'regex:/^[A-Za-z]{5}[0-9]{4}[A-Za-z]$/'],
            'country' => ['nullable', 'string', 'max:100'],
            'ap_email' => ['nullable', 'email'],
            'po_ref' => ['nullable', 'string', 'max:255'],
            'meta_bm_id' => ['nullable', 'string', 'max:32'],
            'waba_existing' => ['nullable', 'string', 'max:64'],
            'tittu_plan_type' => ['nullable', 'string', 'max:64'],
            'tittu_doc_type' => ['nullable', 'string', 'max:64'],
            'tittu_logo_format' => ['nullable', 'string', 'max:32'],
            'primary_use_cases' => ['nullable', 'string', 'max:1000'],
            'site' => ['nullable', 'string', 'max:512'],
        ])->validate();

        $reference = 'COS-'.strtoupper(Str::random(10));
        $loginEmail = strtolower(trim((string) $validated['business_email']));
        $onboardingPayload = json_decode((string) $request->input('payload', ''), true);
        $onboardingPayload = is_array($onboardingPayload) ? $onboardingPayload : null;

        $regDocAbsolutePath = null;
        $file = $request->file('regdoc_file');
        if ($file && $file->isValid()) {
            $storedPath = $file->store('forms/onboarding/regdocs', 'local');
            $regDocAbsolutePath = storage_path('app/'.$storedPath);
        }

        $labeledPayload = $this->buildLabeledPayload($request, $validated, $file?->getClientOriginalName());

        $onboardingRow = CustomerOnboardingSubmission::query()->create([
            'reference' => $reference,
            'company_name' => trim((string) $validated['business_name']),
            'email' => $loginEmail,
            'service_label' => 'WhatsApp Automation - Tittu',
            'status' => 'pending',
            'payload' => [
                'request' => $request->except(['regdoc_file', 'tittu_docs']),
                'normalized_payload' => $labeledPayload,
                'original_payload' => $onboardingPayload,
            ],
            'attachment_paths' => array_values(array_filter([$regDocAbsolutePath])),
            'zoho_response' => [
                'source' => 'formsOnboardingApi',
                'api_flow' => 'minimal_customer_create_update_signature_mail',
                'status' => 'logging_only',
            ],
        ]);

        try {
            $pdfExtract = $this->pdfExtractor->extract($regDocAbsolutePath);
            $businessName = trim((string) $validated['business_name']);
            $resolvedBusinessName = trim((string) ($pdfExtract['business_name'] ?? '')) !== ''
                ? trim((string) $pdfExtract['business_name'])
                : $businessName;
            $resolvedBusinessAddress = trim((string) ($pdfExtract['business_address'] ?? ''));
            if ($resolvedBusinessAddress === '') {
                $resolvedBusinessAddress = null;
            }

            $digits = preg_replace('/\D+/', '', (string) $validated['number']) ?? '';
            if (strlen($digits) > 10) {
                $digits = substr($digits, -10);
            }
            if (strlen($digits) !== 10) {
                throw ValidationException::withMessages([
                    'number' => ['Invalid phone number for auto-registration; expected 10 digits.'],
                ]);
            }

            [$firstName, $lastName] = $this->resolveNames($validated);
            $tempPassword = Str::random(12);
            $metaBusinessId = preg_replace('/\D+/', '', (string) ($validated['meta_bm_id'] ?? '')) ?: null;
            $primaryUseCases = trim((string) ($validated['primary_use_cases'] ?? ''));
            if ($primaryUseCases === '') {
                $primaryUseCases = 'Marketing';
            }

            $planName = $this->planResolver->resolvePlanName($validated, (string) $request->input('tittu_plan_type', ''));
            if ($planName === null) {
                throw new \RuntimeException('Tittu plan type could not be resolved.');
            }
            $plan = $this->planResolver->findActivePlan($planName);
            if ($plan === null) {
                throw new \RuntimeException('Matching active plan not found in plans table (expected '.$planName.').');
            }

            $existingAccess = TenantUserAccess::findActiveByEmail($loginEmail);
            $isNewUser = $existingAccess === null;

            if ($isNewUser) {
                [$tenant, $user] = $this->createTenantAndOwner(
                    loginEmail: $loginEmail,
                    firstName: $firstName,
                    lastName: $lastName,
                    businessName: $resolvedBusinessName,
                    phoneDigits: $digits,
                    tempPassword: $tempPassword,
                    plan: $plan,
                );
            } else {
                $tenant = Tenant::query()->find($existingAccess->tenant_id);
                if ($tenant === null) {
                    throw new \RuntimeException('Existing customer access points to a missing tenant.');
                }
                $user = $this->reuseOwner($tenant, $loginEmail, $tempPassword, $firstName, $lastName, $digits, $plan);
            }

            $this->activatePlanForYear($tenant, $plan);

            tenancy()->initialize($tenant);

            $addr1 = trim((string) ($validated['addr1'] ?? ($pdfExtract['address_line_1'] ?? '')));
            $addr2 = trim((string) ($validated['addr2'] ?? ($pdfExtract['address_line_2'] ?? '')));
            $city = trim((string) ($validated['city'] ?? ($pdfExtract['city'] ?? '')));
            $state = trim((string) ($validated['state'] ?? ($pdfExtract['state'] ?? '')));
            $pin = trim((string) ($validated['pin'] ?? ($pdfExtract['pincode'] ?? '')));
            $pan = strtoupper(trim((string) ($validated['pan'] ?? ($pdfExtract['pan'] ?? ''))));
            $gstin = strtoupper(trim((string) ($validated['gstin'] ?? ($pdfExtract['gstin'] ?? ''))));
            $designation = trim((string) ($validated['designation'] ?? ''));

            $finalBusinessAddress = $resolvedBusinessAddress;
            if ($finalBusinessAddress === null) {
                $finalBusinessAddress = trim(
                    $addr1
                    .($addr2 !== '' ? ', '.$addr2 : '')
                    .($city !== '' ? ', '.$city : '')
                    .($state !== '' ? ', '.$state : '')
                    .($pin !== '' ? ' - '.$pin : '')
                );
                $finalBusinessAddress = $finalBusinessAddress !== '' ? $finalBusinessAddress : null;
            }

            $this->billingAddressService->save([
                'gst_treatment' => $gstin !== '' ? 'Registered Business - Regular' : 'Unregistered Business',
                'company_name' => $resolvedBusinessName,
                'pan' => $pan !== '' ? $pan : null,
                'email' => $loginEmail,
                'phone' => $digits,
                'address_line_1' => $addr1 !== '' ? $addr1 : ($finalBusinessAddress ?: 'Address pending'),
                'address_line_2' => $addr2 !== '' ? $addr2 : null,
                'city' => $city !== '' ? $city : null,
                'state' => $state !== '' ? $state : null,
                'postal_code' => $pin !== '' ? $pin : null,
                'country_code' => 'IN',
            ]);

            $this->upsertIsvTerms(
                businessName: $resolvedBusinessName,
                loginEmail: $loginEmail,
                bmId: $metaBusinessId,
                useCase: $primaryUseCases,
                businessAddress: $finalBusinessAddress,
            );

            $user->forceFill([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $digits,
                'name' => trim($firstName.' '.$lastName),
                'is_active' => true,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            if ($metaBusinessId) {
                $labeledPayload['Business Portfolio ID'] = $metaBusinessId;
            }
            if ($gstin !== '') {
                $labeledPayload['GSTIN/UIN'] = $gstin;
            }

            $readiness = $this->saveOrUpdateReadiness(
                loginEmail: $loginEmail,
                customerName: trim((string) $validated['customer_name']),
                businessName: $resolvedBusinessName,
                website: trim((string) ($validated['site'] ?? '')),
                docType: (string) ($validated['docType'] ?? $validated['tittu_doc_type'] ?? 'GST certificate'),
                labeledPayload: $labeledPayload,
                request: $request,
                userId: (int) $user->id,
                tenantId: (string) $tenant->id,
            );

            $docs = $this->signatureDocuments->generate(
                trim((string) $validated['customer_name']),
                $resolvedBusinessName,
                $digits,
            );

            $loginUrl = (string) config('forms.login_url');

            $onboardingRow->forceFill([
                'status' => 'completed',
                'zoho_response' => [
                    'source' => 'formsOnboardingApi',
                    'api_flow' => 'minimal_customer_create_update_signature_mail',
                    'status' => 'completed',
                    'data' => [
                        'submission_id' => $readiness->id,
                        'user_id' => $user->id,
                        'customer_id' => $tenant->id,
                        'login_email' => $loginEmail,
                        'is_new_user' => $isNewUser,
                        'signature_pdf_generated' => $docs['generated'],
                        'designation' => $designation,
                    ],
                ],
                'attachment_paths' => array_values(array_filter([
                    $regDocAbsolutePath,
                    $docs['signature_path'],
                    $docs['pdf_path'],
                ])),
            ])->save();

            tenancy()->end();

            $this->sendCredentialsMail(
                loginEmail: $loginEmail,
                tempPassword: $tempPassword,
                loginUrl: $loginUrl,
                customerName: trim((string) $validated['customer_name']),
                businessName: $resolvedBusinessName,
                attachments: array_values(array_filter([$docs['pdf_path'], $regDocAbsolutePath])),
                onboardingRow: $onboardingRow,
            );

            try {
                app(AdminNotificationService::class)->notifyNewCustomer(
                    tenantId: (string) $tenant->id,
                    name: $resolvedBusinessName,
                    email: $loginEmail,
                );
            } catch (Throwable) {
                // non-blocking
            }

            return [
                'submission_id' => (int) $readiness->id,
                'user_id' => (int) $user->id,
                'customer_id' => (string) $tenant->id,
                'login_email' => $loginEmail,
                'temp_password' => $tempPassword,
                'login_url' => $loginUrl,
                'is_new_user' => $isNewUser,
                'signature_pdf_generated' => $docs['generated'],
            ];
        } catch (Throwable $e) {
            if (tenancy()->initialized) {
                tenancy()->end();
            }

            $onboardingRow->forceFill([
                'status' => 'failed',
                'zoho_response' => [
                    'source' => 'formsOnboardingApi',
                    'api_flow' => 'minimal_customer_create_update_signature_mail',
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ],
            ])->save();

            throw $e;
        }
    }

    private function mergePayloadIntoRequest(Request $request): void
    {
        $onboardingPayload = json_decode((string) $request->input('payload', ''), true);
        if (! is_array($onboardingPayload)) {
            return;
        }

        $serviceLabel = trim((string) ($onboardingPayload['service_label'] ?? ''));
        $serviceKey = trim((string) ($onboardingPayload['service_key'] ?? ''));
        $isWhatsAppAutomation = strcasecmp($serviceLabel, 'WhatsApp Automation') === 0 || $serviceKey === 'tittu';

        if (! $isWhatsAppAutomation && $serviceKey !== '' && $serviceLabel !== '') {
            throw ValidationException::withMessages([
                'service_key' => ['This API flow is only for WhatsApp Automation service.'],
            ]);
        }

        $step1 = is_array($onboardingPayload['step1'] ?? null) ? $onboardingPayload['step1'] : [];
        $setup = is_array($onboardingPayload['setup'] ?? null) ? $onboardingPayload['setup'] : [];

        $firstName = trim((string) ($step1['first_name'] ?? ''));
        $lastName = trim((string) ($step1['last_name'] ?? ''));
        $fullName = trim($firstName.' '.$lastName);
        $phoneCode = trim((string) ($step1['phone_code'] ?? '+91'));
        $phone = trim((string) ($step1['phone'] ?? ''));
        $bmIdDigits = preg_replace('/\D+/', '', (string) ($setup['meta_bm_id'] ?? ''));

        $rawDocType = trim((string) (
            $setup['registration_doc_type']
            ?? $setup['tittu_doc_type']
            ?? $setup['tittu_gst_udyam_type']
            ?? ''
        ));
        $docTypeMap = [
            'gst' => 'GST certificate',
            'udyam' => 'Udyam MSME certificate',
            'pan' => 'PAN Card',
        ];
        $resolvedDocType = $docTypeMap[strtolower($rawDocType)] ?? ($rawDocType !== '' ? $rawDocType : 'GST certificate');

        $wabaRaw = trim((string) ($setup['waba_existing'] ?? ''));
        $wabaMap = [
            'yes_migrate' => 'Yes - migrating to Tittu',
            'no_new' => 'No - new number',
        ];
        $resolvedWabaExisting = $wabaMap[strtolower($wabaRaw)] ?? $wabaRaw;

        $planRaw = trim((string) ($setup['tittu_plan_type'] ?? ''));
        $planMap = [
            'ginger_basic' => 'Ginger Basic',
            'ginger_advanced' => 'Ginger Advance',
        ];
        $resolvedPlanType = $planMap[strtolower($planRaw)] ?? $planRaw;

        $primaryUseCasesRaw = $setup['primary_use_cases'] ?? $setup['use_cases'] ?? ($setup['field_5'] ?? '');
        if (is_array($primaryUseCasesRaw)) {
            $primaryUseCasesRaw = implode(', ', array_values(array_filter(array_map(
                static fn ($x) => trim((string) $x),
                $primaryUseCasesRaw,
            ))));
        }
        $resolvedPrimaryUseCases = trim((string) $primaryUseCasesRaw);

        $request->merge([
            'customer_name' => $fullName !== '' ? $fullName : $request->input('customer_name'),
            'customer_email' => trim((string) ($step1['email'] ?? $request->input('customer_email'))),
            'business_name' => trim((string) ($step1['company'] ?? $request->input('business_name'))),
            'business_email' => trim((string) ($step1['email'] ?? $request->input('business_email'))),
            'docType' => $resolvedDocType,
            'number' => trim($phoneCode.$phone) !== '' ? trim($phoneCode.$phone) : $request->input('number'),
            'whatsapp_display_name' => trim((string) ($setup['whatsapp_display_name'] ?? ($step1['company'] ?? $request->input('whatsapp_display_name')))),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'designation' => trim((string) ($step1['designation'] ?? '')),
            'gstin' => strtoupper(trim((string) ($step1['gstin'] ?? ''))),
            'addr1' => trim((string) ($step1['addr1'] ?? '')),
            'addr2' => trim((string) ($step1['addr2'] ?? '')),
            'city' => trim((string) ($step1['city'] ?? '')),
            'state' => trim((string) ($step1['state'] ?? '')),
            'pin' => trim((string) ($step1['pin'] ?? '')),
            'pan' => strtoupper(trim((string) ($step1['pan'] ?? ''))),
            'country' => trim((string) ($step1['country'] ?? 'India')),
            'ap_email' => trim((string) ($step1['accounts_payable_email'] ?? ($step1['ap_email'] ?? ''))),
            'po_ref' => trim((string) ($step1['po_ref'] ?? '')),
            'meta_bm_id' => $bmIdDigits,
            'waba_existing' => $resolvedWabaExisting,
            'tittu_plan_type' => $resolvedPlanType,
            'tittu_doc_type' => $resolvedDocType,
            'tittu_logo_format' => trim((string) ($setup['tittu_logo_format'] ?? '')),
            'primary_use_cases' => $resolvedPrimaryUseCases,
            'site' => trim((string) ($step1['website'] ?? ($step1['website_url'] ?? $request->input('site', '')))),
        ]);

        if (! $request->hasFile('regdoc_file') && $request->hasFile('tittu_docs')) {
            $docs = $request->file('tittu_docs');
            if (is_array($docs) && isset($docs[0]) && $docs[0] && $docs[0]->isValid()) {
                $request->files->set('regdoc_file', $docs[0]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function buildLabeledPayload(Request $request, array $validated, ?string $regDocName): array
    {
        $payload = [
            'Customer Name' => $request->input('customer_name'),
            'Customer Email' => $request->input('customer_email'),
            'Business Name' => $request->input('business_name'),
            'Business Email' => $request->input('business_email'),
            'WhatsApp Display Name' => $request->input('whatsapp_display_name'),
            'Registration document' => $request->input('docType'),
            'Phone Number' => $request->input('number'),
            'First Name' => $request->input('first_name'),
            'Last Name' => $request->input('last_name'),
            'Designation' => $request->input('designation'),
            'GSTIN' => $request->input('gstin'),
            'Address Line 1' => $request->input('addr1'),
            'Address Line 2' => $request->input('addr2'),
            'City' => $request->input('city'),
            'State' => $request->input('state'),
            'Pincode' => $request->input('pin'),
            'PAN Number' => $request->input('pan'),
            'Country' => $request->input('country'),
            'Accounts Payable Email' => $request->input('ap_email'),
            'PO Reference Number' => $request->input('po_ref'),
            'Meta Business Manager ID' => $request->input('meta_bm_id'),
            'Existing WABA Account' => $request->input('waba_existing'),
            'Tittu Plan Type' => $request->input('tittu_plan_type'),
            'Tittu Doc Type' => $request->input('tittu_doc_type'),
            'Tittu Logo Format' => $request->input('tittu_logo_format'),
            'Primary Use Cases' => $request->input('primary_use_cases'),
        ];

        if ($regDocName) {
            $payload['Registration document file'] = $regDocName;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: string, 1: string}
     */
    private function resolveNames(array $validated): array
    {
        $customerName = trim((string) $validated['customer_name']);
        $firstName = trim((string) ($validated['first_name'] ?? ''));
        $lastName = trim((string) ($validated['last_name'] ?? ''));

        if ($firstName === '' || $lastName === '') {
            $nameParts = preg_split('/\s+/', $customerName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $firstName = $firstName !== '' ? $firstName : (string) ($nameParts[0] ?? $customerName);
            $lastName = $lastName !== '' ? $lastName : (string) ($nameParts[count($nameParts) - 1] ?? 'User');
        }

        if ($firstName === $lastName) {
            $lastName = 'User';
        }

        return [$firstName, $lastName];
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function createTenantAndOwner(
        string $loginEmail,
        string $firstName,
        string $lastName,
        string $businessName,
        string $phoneDigits,
        string $tempPassword,
        Plan $plan,
    ): array {
        $tenantSlug = $this->tenantSlugService->generateUnique($businessName !== '' ? $businessName : $loginEmail);
        $validityDays = max(365, $plan->resolvedValidityDays());

        $tenant = Tenant::query()->create([
            'id' => $tenantSlug,
            'database_name' => $this->databaseNamingService->forTenantId($tenantSlug),
            'name' => $businessName,
            'company_name' => $businessName,
            'email' => $loginEmail,
            'phone' => PhoneNormalizer::normalize($phoneDigits) ?? $phoneDigits,
            'status' => TenantStatus::Active,
            'plan_id' => $plan->id,
            'timezone' => 'Asia/Kolkata',
            'locale' => 'en',
            'country_code' => 'IN',
            'provisioned_at' => now(),
            'settings' => [
                'valid_until' => now()->addDays($validityDays)->toDateString(),
                'source' => 'forms_onboarding',
            ],
        ]);

        $tenant->domains()->create([
            'domain' => $tenantSlug,
            'is_primary' => true,
        ]);

        // TenantCreated jobs create/migrate DB; wait until ready.
        app(\App\Domains\Tenancy\Services\TenantProvisioner::class)->ensureDatabase($tenant);

        tenancy()->initialize($tenant);

        $user = User::query()->create([
            'name' => trim($firstName.' '.$lastName),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $loginEmail,
            'phone' => $phoneDigits,
            'password' => $tempPassword,
            'role' => UserRole::Owner,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        tenancy()->end();

        TenantUserAccess::query()->create([
            'email' => $loginEmail,
            'phone' => PhoneNormalizer::normalize($phoneDigits),
            'tenant_id' => $tenant->id,
            'account_type' => TenantUserAccountType::Owner,
            'is_active' => true,
        ]);

        return [$tenant->fresh() ?? $tenant, $user];
    }

    private function reuseOwner(
        Tenant $tenant,
        string $loginEmail,
        string $tempPassword,
        string $firstName,
        string $lastName,
        string $phoneDigits,
        Plan $plan,
    ): User {
        app(\App\Domains\Tenancy\Services\TenantProvisioner::class)->ensureDatabase($tenant);

        $tenant->forceFill([
            'plan_id' => $plan->id,
            'company_name' => $tenant->company_name ?: $tenant->name,
            'status' => TenantStatus::Active,
        ])->save();

        tenancy()->initialize($tenant);

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$loginEmail])
            ->first()
            ?? User::query()->where('role', UserRole::Owner)->orderBy('id')->first();

        if ($user === null) {
            $user = User::query()->create([
                'name' => trim($firstName.' '.$lastName),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $loginEmail,
                'phone' => $phoneDigits,
                'password' => $tempPassword,
                'role' => UserRole::Owner,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
        } else {
            $user->forceFill([
                'password' => $tempPassword,
                'is_active' => true,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $phoneDigits,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        }

        tenancy()->end();

        return $user;
    }

    private function activatePlanForYear(Tenant $tenant, Plan $plan): void
    {
        $validityDays = max(365, $plan->resolvedValidityDays());
        $endsAt = now()->addDays($validityDays);

        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $settings['valid_until'] = $endsAt->toDateString();
        $tenant->forceFill([
            'plan_id' => $plan->id,
            'settings' => $settings,
        ])->save();

        tenancy()->initialize($tenant);

        $subscription = Subscription::query()
            ->where('plan_id', $plan->id)
            ->where('status', SubscriptionStatus::Active)
            ->latest('id')
            ->first();

        if ($subscription === null) {
            Subscription::query()->create([
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'amount' => $plan->price,
                'currency' => $plan->currency ?? 'INR',
                'starts_at' => now(),
                'ends_at' => $endsAt->copy()->endOfDay(),
                'metadata' => [
                    'source' => 'forms_onboarding',
                    'paid' => true,
                ],
            ]);
        } else {
            $subscription->forceFill([
                'ends_at' => $endsAt->copy()->endOfDay(),
                'status' => SubscriptionStatus::Active,
                'metadata' => array_merge(
                    is_array($subscription->metadata) ? $subscription->metadata : [],
                    ['source' => 'forms_onboarding', 'paid' => true],
                ),
            ])->save();
        }

        tenancy()->end();
    }

    private function upsertIsvTerms(
        string $businessName,
        string $loginEmail,
        ?string $bmId,
        string $useCase,
        ?string $businessAddress,
    ): void {
        $term = IsvTermsAcceptance::query()->latest('id')->first() ?? new IsvTermsAcceptance([
            'country_code' => 'IN',
            'status' => 'Unverified',
        ]);

        $term->fill([
            'business_name' => $businessName,
            'website_email' => $loginEmail,
            'bm_id' => $bmId,
            'use_case' => $useCase,
            'business_address' => $businessAddress,
            'country_code' => 'IN',
            'status' => $term->status ?: 'Unverified',
        ]);
        $term->save();
    }

    /**
     * @param  array<string, mixed>  $labeledPayload
     */
    private function saveOrUpdateReadiness(
        string $loginEmail,
        string $customerName,
        string $businessName,
        string $website,
        string $docType,
        array $labeledPayload,
        Request $request,
        int $userId,
        string $tenantId,
    ): CustomerReadinessSubmission {
        $existing = CustomerReadinessSubmission::query()
            ->whereRaw('LOWER(business_email) = ?', [$loginEmail])
            ->first();

        $meta = [
            'source' => 'api',
            'api_flow' => 'minimal_customer_create_update_signature_mail',
            'last_api_at' => now()->toIso8601String(),
            'eligible' => true,
            'action' => 'ready',
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'ip' => $request->ip(),
            'prior_web_submission' => $existing !== null,
        ];

        if ($existing !== null) {
            $existing->update([
                'customer_name' => $customerName,
                'customer_email' => $loginEmail,
                'business_name' => $businessName,
                'business_email' => $loginEmail,
                'website' => $website !== '' ? $website : $existing->website,
                'doc_type' => $this->docTypeStorageKey($docType),
                'status' => 'eligible',
                'data' => array_merge(is_array($existing->data) ? $existing->data : [], $labeledPayload),
                'meta' => array_merge(is_array($existing->meta) ? $existing->meta : [], $meta),
            ]);

            return $existing->fresh() ?? $existing;
        }

        return CustomerReadinessSubmission::query()->create([
            'customer_name' => $customerName,
            'customer_email' => $loginEmail,
            'business_name' => $businessName,
            'business_email' => $loginEmail,
            'website' => $website !== '' ? $website : null,
            'doc_type' => $this->docTypeStorageKey($docType),
            'status' => 'eligible',
            'data' => $labeledPayload,
            'meta' => $meta,
        ]);
    }

    private function docTypeStorageKey(string $docType): string
    {
        $lower = strtolower($docType);
        if (str_contains($lower, 'udyam') || str_contains($lower, 'msme')) {
            return 'udyam';
        }
        if (str_contains($lower, 'pan')) {
            return 'pan';
        }

        return 'gst';
    }

    /**
     * @param  list<string>  $attachments
     */
    private function sendCredentialsMail(
        string $loginEmail,
        string $tempPassword,
        string $loginUrl,
        string $customerName,
        string $businessName,
        array $attachments,
        CustomerOnboardingSubmission $onboardingRow,
    ): void {
        try {
            Mail::to($loginEmail)->send(new FormsOnboardingCredentialsMail(
                loginEmail: $loginEmail,
                tempPassword: $tempPassword,
                loginUrl: $loginUrl,
                customerName: $customerName,
                businessName: $businessName,
                attachmentPaths: $attachments,
            ));

            $meta = is_array($onboardingRow->zoho_response) ? $onboardingRow->zoho_response : [];
            $meta['mail_status'] = 'sent';
            $meta['mail_sent_at'] = now()->toIso8601String();
            $onboardingRow->forceFill(['zoho_response' => $meta])->save();
        } catch (Throwable $e) {
            Log::error('Forms onboarding credentials mail failed (account still created)', [
                'login_email' => $loginEmail,
                'error' => $e->getMessage(),
            ]);

            $meta = is_array($onboardingRow->zoho_response) ? $onboardingRow->zoho_response : [];
            $meta['mail_status'] = 'failed';
            $meta['mail_error'] = mb_substr($e->getMessage(), 0, 2000);
            $onboardingRow->forceFill(['zoho_response' => $meta])->save();
        }
    }
}
