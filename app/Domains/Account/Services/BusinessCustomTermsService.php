<?php

declare(strict_types=1);

namespace App\Domains\Account\Services;

use App\Domains\Account\Mail\BusinessCustomTermsReviewMail;
use App\Domains\Admin\Services\AdminNotificationService;
use App\Enums\AdminNotificationType;
use App\Enums\BusinessCustomTermStatus;
use App\Enums\TenantStatus;
use App\Models\Admin;
use App\Models\BusinessCustomTerm;
use App\Models\Tenant;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class BusinessCustomTermsService
{
    public function __construct(
        private readonly AdminNotificationService $adminNotifications,
    ) {}

    public function forTenant(string $tenantId): ?BusinessCustomTerm
    {
        if (! Schema::hasTable('business_custom_terms')) {
            return null;
        }

        return BusinessCustomTerm::query()
            ->where('tenant_id', $tenantId)
            ->first();
    }

    /**
     * @param  array{title: string, body: string, submit_for_review?: bool}  $data
     */
    public function saveDraft(Tenant $tenant, array $data): BusinessCustomTerm
    {
        $this->ensureTableReady();

        $max = (int) config('legal.custom_terms.max_chars', 50000);
        $body = trim($data['body']);
        $title = trim($data['title']);

        if ($title === '' || $body === '') {
            $messages = [];
            if ($title === '') {
                $messages['title'] = 'Title is required.';
            }
            if ($body === '') {
                $messages['body'] = 'Terms body is required.';
            }
            throw ValidationException::withMessages($messages);
        }

        if (mb_strlen($body) > $max) {
            throw ValidationException::withMessages([
                'body' => "Terms must be at most {$max} characters.",
            ]);
        }

        $term = $this->forTenant((string) $tenant->id) ?? new BusinessCustomTerm([
            'tenant_id' => (string) $tenant->id,
            'version' => 0,
        ]);

        $changed = $term->exists
            && ($term->title !== $title || $term->body !== $body);

        $term->title = $title;
        $term->body = $body;

        if (! $term->exists || $changed) {
            $term->version = (int) $term->version + 1;
            $term->admin_note = null;
            $term->reviewed_at = null;
            $term->reviewed_by_admin_id = null;
            $term->published_at = null;
        }

        $submit = (bool) ($data['submit_for_review'] ?? false);
        if ($submit) {
            $term->status = BusinessCustomTermStatus::PendingReview;
            $term->submitted_at = now();
        } elseif (! $term->exists || $changed || $term->status === BusinessCustomTermStatus::Removed) {
            $term->status = BusinessCustomTermStatus::Draft;
        }

        $term->save();

        if ($submit) {
            $this->notifyAdminsPending($tenant, $term);
        }

        return $term->fresh();
    }

    public function approve(BusinessCustomTerm $term, Admin $admin, ?string $note = null): BusinessCustomTerm
    {
        $this->ensureTableReady();

        $term->forceFill([
            'status' => BusinessCustomTermStatus::Approved,
            'admin_note' => $note,
            'reviewed_by_admin_id' => $admin->id,
            'reviewed_at' => now(),
            'published_at' => now(),
        ])->save();

        $this->mailTenant($term, 'approved', $note);

        return $term->fresh();
    }

    public function reject(BusinessCustomTerm $term, Admin $admin, string $note, bool $suspendTenant = false): BusinessCustomTerm
    {
        $this->ensureTableReady();

        $note = trim($note);
        if ($note === '') {
            throw ValidationException::withMessages([
                'admin_note' => 'Please explain why these terms were rejected.',
            ]);
        }

        $term->forceFill([
            'status' => BusinessCustomTermStatus::Rejected,
            'admin_note' => $note,
            'reviewed_by_admin_id' => $admin->id,
            'reviewed_at' => now(),
            'published_at' => null,
        ])->save();

        if ($suspendTenant) {
            $this->suspendTenant($term->tenant_id);
        }

        $this->mailTenant($term, 'rejected', $note, $suspendTenant);

        return $term->fresh();
    }

    public function remove(BusinessCustomTerm $term, Admin $admin, string $note, bool $suspendTenant = false): BusinessCustomTerm
    {
        $this->ensureTableReady();

        $note = trim($note);
        if ($note === '') {
            throw ValidationException::withMessages([
                'admin_note' => 'Please explain why these terms were removed.',
            ]);
        }

        $term->forceFill([
            'status' => BusinessCustomTermStatus::Removed,
            'admin_note' => $note,
            'reviewed_by_admin_id' => $admin->id,
            'reviewed_at' => now(),
            'published_at' => null,
        ])->save();

        if ($suspendTenant) {
            $this->suspendTenant($term->tenant_id);
        }

        $this->mailTenant($term, 'removed', $note, $suspendTenant);

        return $term->fresh();
    }

    private function ensureTableReady(): void
    {
        if (! Schema::hasTable('business_custom_terms')) {
            throw new RuntimeException('Business custom terms are not available until migrations are run.');
        }
    }

    private function suspendTenant(string $tenantId): void
    {
        $tenant = Tenant::query()->find($tenantId);
        if (! $tenant) {
            return;
        }

        $tenant->status = TenantStatus::Suspended;
        $tenant->suspended_at = $tenant->suspended_at ?? now();
        $tenant->save();
    }

    private function notifyAdminsPending(Tenant $tenant, BusinessCustomTerm $term): void
    {
        $label = $tenant->company_name ?: $tenant->name ?: $tenant->email ?: $tenant->id;

        $this->adminNotifications->notify(
            AdminNotificationType::System,
            'Custom terms pending review',
            "{$label} submitted customer-facing terms (v{$term->version}) for review.",
            route('admin.customers.show', $tenant),
            [
                'tenant_id' => (string) $tenant->id,
                'term_uuid' => $term->uuid,
                'kind' => 'business_custom_terms_pending',
            ],
        );
    }

    private function mailTenant(BusinessCustomTerm $term, string $action, ?string $note, bool $suspended = false): void
    {
        $tenant = $term->tenant ?? Tenant::query()->find($term->tenant_id);
        $email = $tenant?->email;
        if (! is_string($email) || $email === '') {
            return;
        }

        try {
            Mail::to($email)->send(new BusinessCustomTermsReviewMail(
                action: $action,
                businessName: (string) ($tenant->company_name ?: $tenant->name ?: 'your business'),
                termsTitle: (string) $term->title,
                adminNote: $note,
                suspended: $suspended,
            ));
        } catch (\Throwable) {
            // Non-blocking — admin action already persisted.
        }
    }
}
