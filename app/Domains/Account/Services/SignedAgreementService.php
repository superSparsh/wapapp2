<?php

declare(strict_types=1);

namespace App\Domains\Account\Services;

use App\Models\CustomerOnboardingSubmission;
use App\Models\Tenant;
use Illuminate\Support\Facades\File;

class SignedAgreementService
{
    /**
     * @return array{submission: ?CustomerOnboardingSubmission, pdf_path: ?string, pdf_name: ?string}
     */
    public function resolveForTenant(Tenant $tenant): array
    {
        $submission = $this->findSubmission($tenant);
        $pdfPath = $this->firstPdfPath($submission?->attachment_paths ?? []);

        return [
            'submission' => $submission,
            'pdf_path' => $pdfPath,
            'pdf_name' => $pdfPath !== null ? basename($pdfPath) : null,
        ];
    }

    public function absolutePdfPathForTenant(Tenant $tenant): ?string
    {
        $resolved = $this->resolveForTenant($tenant);
        $path = $resolved['pdf_path'];

        if ($path === null || ! is_file($path)) {
            return null;
        }

        $signaturesRoot = realpath(storage_path('app/public/forms/signatures'));
        $real = realpath($path);
        if ($signaturesRoot === false || $real === false || ! str_starts_with($real, $signaturesRoot)) {
            // Also allow registration docs stored alongside if they are PDFs under forms/
            $formsRoot = realpath(storage_path('app/public/forms'));
            if ($formsRoot === false || ! str_starts_with($real, $formsRoot)) {
                return null;
            }
        }

        return $real;
    }

    private function findSubmission(Tenant $tenant): ?CustomerOnboardingSubmission
    {
        $tenantId = (string) $tenant->id;
        $email = strtolower(trim((string) ($tenant->email ?? '')));

        $byTenant = CustomerOnboardingSubmission::query()
            ->where('status', 'completed')
            ->where('zoho_response->data->customer_id', $tenantId)
            ->latest('id')
            ->first();

        if ($byTenant) {
            return $byTenant;
        }

        if ($email === '') {
            return null;
        }

        return CustomerOnboardingSubmission::query()
            ->where('status', 'completed')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->latest('id')
            ->first();
    }

    /**
     * @param  list<mixed>|array<int, mixed>  $paths
     */
    private function firstPdfPath(array $paths): ?string
    {
        foreach ($paths as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }
            if (! str_ends_with(strtolower($path), '.pdf')) {
                continue;
            }
            if (is_file($path) || File::exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
