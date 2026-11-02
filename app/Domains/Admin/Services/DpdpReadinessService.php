<?php

declare(strict_types=1);

namespace App\Domains\Admin\Services;

use App\Domains\Account\Services\ConsentEventService;
use App\Domains\Account\Services\DataPrincipalRequestService;
use App\Domains\Account\Services\SignedAgreementService;
use App\Enums\BusinessCustomTermStatus;
use App\Enums\TenantStatus;
use App\Models\BusinessCustomTerm;
use App\Models\Tenant;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Checklist of DPDP / legal building blocks for admin visibility.
 * Pages stay draft / login-gated until leadership publishes publicly.
 */
class DpdpReadinessService
{
    public function __construct(
        private readonly SignedAgreementService $signedAgreements,
        private readonly ConsentEventService $consentEvents,
        private readonly DataPrincipalRequestService $rightsRequests,
    ) {}

    /**
     * @return array{
     *   summary: array{done: int, partial: int, pending: int, total: int},
     *   items: list<array{key: string, label: string, status: string, detail: string, href: ?string}>,
     *   config: array<string, mixed>
     * }
     */
    public function platformChecklist(): array
    {
        $grievance = (string) config('legal.grievance.email', '');
        $meta = config('legal.meta_policies', []);
        $metaKeys = ['business_terms', 'commerce_policy', 'business_policy', 'program_addendum'];
        $metaOk = true;
        foreach ($metaKeys as $key) {
            $url = (string) ($meta[$key]['pdf_url'] ?? $meta[$key]['url'] ?? '');
            if ($url === '') {
                $metaOk = false;
                break;
            }
        }

        $customTermsTable = $this->customTermsTableReady();
        $privacyRoute = Route::has('profile.privacy-policy');
        $termsRoute = Route::has('profile.terms');
        $breachRoute = Route::has('profile.breach-playbook');
        $businessTermsRoute = Route::has('profile.business-terms');
        $dataDeletionRoute = Route::has('profile.data-deletion');
        $rightsRoute = Route::has('profile.privacy-rights');
        $adminRightsRoute = Route::has('admin.rights-requests.index');
        $publicPublish = (bool) config('legal.public_publish', false);
        $cookieBanner = (bool) config('legal.cookies.banner_enabled', false);
        $minAge = (int) config('legal.children.minimum_age', 18);
        $consentReady = $this->consentEvents->tableReady();
        $rightsTableReady = $this->rightsRequests->tableReady();

        $items = [
            [
                'key' => 'privacy_terms_breach',
                'label' => 'Privacy / Terms / Breach playbook pages',
                'status' => ($privacyRoute && $termsRoute && $breachRoute) ? 'done' : 'pending',
                'detail' => 'Login-gated drafts (not public footer yet). Auth users can open profile legal URLs.',
                'href' => $privacyRoute ? route('profile.privacy-policy') : null,
            ],
            [
                'key' => 'grievance',
                'label' => 'Grievance / DPDP contact',
                'status' => $grievance !== '' ? 'done' : 'pending',
                'detail' => $grievance !== ''
                    ? 'Configured as '.$grievance.' (config/legal.php / LEGAL_GRIEVANCE_EMAIL).'
                    : 'Set LEGAL_GRIEVANCE_EMAIL in .env.',
                'href' => null,
            ],
            [
                'key' => 'meta_policies',
                'label' => 'Meta WhatsApp policy links at signup',
                'status' => $metaOk ? 'done' : 'partial',
                'detail' => $metaOk
                    ? 'Business Terms, Commerce Policy, Business Policy, and Program Addendum URLs are set.'
                    : 'One or more LEGAL_META_* URLs are empty.',
                'href' => Route::has('signup.step-3') ? route('signup.step-3') : null,
            ],
            [
                'key' => 'custom_terms_workflow',
                'label' => 'Business custom terms + admin review',
                'status' => ($customTermsTable && $businessTermsRoute) ? 'done' : 'pending',
                'detail' => $customTermsTable
                    ? 'Draft / submit / approve / reject / remove (+ optional suspend) and PDF download available.'
                    : 'Run central migration for business_custom_terms.',
                'href' => null,
            ],
            [
                'key' => 'signed_onboarding_pdf',
                'label' => 'Signed onboarding agreement PDF',
                'status' => 'done',
                'detail' => 'Resolved from forms.tekprocloud.com onboarding submissions when PDF is on file.',
                'href' => null,
            ],
            [
                'key' => 'collection_notice',
                'label' => 'Collection-point notice (signup)',
                'status' => 'done',
                'detail' => 'Reusable notice component + acknowledgement checkbox on register profile step.',
                'href' => Route::has('signup.register') ? route('signup.register') : null,
            ],
            [
                'key' => 'age_gate',
                'label' => 'Children / age gate',
                'status' => $minAge >= 18 ? 'done' : 'partial',
                'detail' => 'Register requires affirmation of age ≥ '.$minAge.' (LEGAL_MINIMUM_AGE). Logged as consent event.',
                'href' => null,
            ],
            [
                'key' => 'consent_events',
                'label' => 'Consent event ledger',
                'status' => $consentReady ? 'done' : 'pending',
                'detail' => $consentReady
                    ? 'Central consent_events table + ConsentEventService; signup consents are mirrored.'
                    : 'Run migration create_consent_events_table.',
                'href' => null,
            ],
            [
                'key' => 'rights_workflow',
                'label' => 'Rights + grievance request workflow',
                'status' => ($rightsRoute && $adminRightsRoute && $rightsTableReady) ? 'done' : 'pending',
                'detail' => ($rightsRoute && $rightsTableReady)
                    ? 'Profile Privacy rights form + Admin Rights / grievance queue (login-gated).'
                    : 'Run migration create_data_principal_requests_table and deploy routes.',
                'href' => $adminRightsRoute ? route('admin.rights-requests.index') : null,
            ],
            [
                'key' => 'data_deletion',
                'label' => 'Data deletion / export (account)',
                'status' => $dataDeletionRoute ? 'partial' : 'pending',
                'detail' => $dataDeletionRoute
                    ? 'Feature routes exist; keep as internal until published in profile nav.'
                    : 'Data deletion routes missing.',
                'href' => null,
            ],
            [
                'key' => 'cookie_banner',
                'label' => 'Cookie / SDK consent banner',
                'status' => ($cookieBanner && $publicPublish) ? 'done' : 'partial',
                'detail' => 'Component ready; LEGAL_COOKIE_BANNER_ENABLED and LEGAL_PUBLIC_PUBLISH both required to show.',
                'href' => null,
            ],
            [
                'key' => 'ai_disclosure',
                'label' => 'AI processing disclosure',
                'status' => 'done',
                'detail' => 'AI processing notice on Create/Edit AI Bot. Privacy draft also covers processors.',
                'href' => Route::has('ai-bots.create') ? route('ai-bots.create') : null,
            ],
            [
                'key' => 'public_publish',
                'label' => 'Public / login footer publish',
                'status' => $publicPublish ? 'done' : 'pending',
                'detail' => $publicPublish
                    ? 'LEGAL_PUBLIC_PUBLISH=true — ensure footer/login links are wired before go-live.'
                    : 'Intentionally draft: do not link Privacy/Terms on public login/footer until leadership sign-off.',
                'href' => null,
            ],
            [
                'key' => 'org_encryption_evidence',
                'label' => 'Org: encryption evidence',
                'status' => 'pending',
                'detail' => 'Attach hosting/DB encryption-at-rest and TLS evidence outside the app (ops register).',
                'href' => null,
            ],
            [
                'key' => 'org_vendor_dpas',
                'label' => 'Org: vendor DPA register',
                'status' => 'pending',
                'detail' => 'Meta, Alibaba CAMS, Tittu, hosting — keep signed DPAs / SCCs on file.',
                'href' => null,
            ],
            [
                'key' => 'org_training_drills',
                'label' => 'Org: training + breach drills',
                'status' => 'pending',
                'detail' => 'Staff DPDP training records and at least one breach-playbook tabletop drill.',
                'href' => $breachRoute ? route('profile.breach-playbook') : null,
            ],
            [
                'key' => 'org_sdf',
                'label' => 'Org: Significant Data Fiduciary',
                'status' => 'partial',
                'detail' => 'Treat as N/A until notified as SDF; revisit Board obligations if classification changes.',
                'href' => null,
            ],
        ];

        return [
            'summary' => $this->summarize($items),
            'items' => $items,
            'config' => [
                'company' => (string) config('legal.company_legal_name'),
                'product' => (string) config('legal.product_name'),
                'effective_date' => (string) config('legal.effective_date'),
                'grievance_email' => $grievance,
                'breach_owner' => (string) config('legal.breach.owner_email'),
                'public_publish' => $publicPublish,
                'minimum_age' => $minAge,
            ],
        ];
    }

    /**
     * @return array{
     *   summary: array{done: int, partial: int, pending: int, total: int},
     *   items: list<array{key: string, label: string, status: string, detail: string, href: ?string}>
     * }
     */
    public function tenantChecklist(Tenant $tenant): array
    {
        $signed = $this->signedAgreements->resolveForTenant($tenant);
        $hasSignedPdf = ! empty($signed['pdf_path']);

        $term = null;
        if ($this->customTermsTableReady()) {
            $term = BusinessCustomTerm::query()->where('tenant_id', $tenant->id)->first();
        }

        $termStatus = 'pending';
        $termDetail = 'No custom terms created yet.';
        if ($term instanceof BusinessCustomTerm) {
            $termStatus = match ($term->status) {
                BusinessCustomTermStatus::Approved => 'done',
                BusinessCustomTermStatus::PendingReview => 'partial',
                BusinessCustomTermStatus::Draft => 'partial',
                BusinessCustomTermStatus::Rejected, BusinessCustomTermStatus::Removed => 'pending',
                default => 'partial',
            };
            $termDetail = ($term->status?->label() ?? 'Unknown').' · v'.$term->version
                .($term->title ? ' — '.$term->title : '');
        }

        $items = [
            [
                'key' => 'signed_pdf',
                'label' => 'Signed onboarding agreement PDF',
                'status' => $hasSignedPdf ? 'done' : 'pending',
                'detail' => $hasSignedPdf
                    ? 'On file: '.($signed['pdf_name'] ?? 'agreement.pdf')
                    : 'No PDF linked from forms onboarding submission yet (forms.tekprocloud.com).',
                'href' => null,
            ],
            [
                'key' => 'custom_terms',
                'label' => 'Custom customer terms',
                'status' => $termStatus,
                'detail' => $termDetail,
                'href' => $term ? route('admin.customers.custom-terms.pdf', $tenant) : null,
            ],
            [
                'key' => 'account_status',
                'label' => 'Account status',
                'status' => $tenant->status === TenantStatus::Active ? 'done' : 'partial',
                'detail' => 'Tenant status: '.($tenant->status instanceof TenantStatus ? $tenant->status->value : (string) $tenant->status),
                'href' => null,
            ],
        ];

        return [
            'summary' => $this->summarize($items),
            'items' => $items,
        ];
    }

    /**
     * @param  list<array{status: string}>  $items
     * @return array{done: int, partial: int, pending: int, total: int}
     */
    private function summarize(array $items): array
    {
        $done = 0;
        $partial = 0;
        $pending = 0;
        foreach ($items as $item) {
            match ($item['status']) {
                'done' => $done++,
                'partial' => $partial++,
                default => $pending++,
            };
        }

        return [
            'done' => $done,
            'partial' => $partial,
            'pending' => $pending,
            'total' => count($items),
        ];
    }

    private function customTermsTableReady(): bool
    {
        $connection = (string) config('tenancy.database.central_connection', config('database.default'));

        return Schema::connection($connection)->hasTable('business_custom_terms');
    }
}
