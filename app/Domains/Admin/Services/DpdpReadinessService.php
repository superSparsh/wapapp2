<?php

declare(strict_types=1);

namespace App\Domains\Admin\Services;

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
    ) {}

    /**
     * @return array{
     *   summary: array{done: int, partial: int, pending: int, total: int},
     *   items: list<array{key: string, label: string, status: string, detail: string, href: ?string}>
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
                'key' => 'data_deletion',
                'label' => 'Data deletion / export (account)',
                'status' => $dataDeletionRoute ? 'partial' : 'pending',
                'detail' => $dataDeletionRoute
                    ? 'Feature routes exist; keep as internal until published in profile nav.'
                    : 'Data deletion routes missing.',
                'href' => null,
            ],
            [
                'key' => 'public_publish',
                'label' => 'Public / login footer publish',
                'status' => 'pending',
                'detail' => 'Intentionally draft: do not link Privacy/Terms on public login/footer until leadership sign-off.',
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
