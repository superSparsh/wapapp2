<?php

declare(strict_types=1);

namespace App\Domains\Audience\Jobs;

use App\Domains\Audience\Services\ContactImportService;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Queued CSV import (legacy parity for 40k+ rows).
 */
class ImportContactsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 7200;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $storedPath,
        public readonly int $mailListId,
        public readonly bool $forceSendOptIn = false,
        public readonly string $disk = 'local',
    ) {}

    public function handle(ContactImportService $importService): void
    {
        $alreadyOnTenant = tenancy()->initialized
            && (string) tenant('id') === $this->tenantId;

        if (! $alreadyOnTenant) {
            $tenant = tenancy()->central(fn () => Tenant::query()->find($this->tenantId));

            if ($tenant === null) {
                Log::error('ImportContactsJob: tenant not found', ['tenant_id' => $this->tenantId]);

                return;
            }

            tenancy()->initialize($tenant);
        }

        try {
            $disk = Storage::disk($this->disk);

            if (! $disk->exists($this->storedPath)) {
                Log::error('ImportContactsJob: file missing', [
                    'tenant_id' => $this->tenantId,
                    'path' => $this->storedPath,
                ]);

                return;
            }

            $absolute = $disk->path($this->storedPath);
            $result = $importService->importFromPath(
                $absolute,
                $this->mailListId,
                $this->forceSendOptIn,
            );

            Log::info('ImportContactsJob completed', [
                'tenant_id' => $this->tenantId,
                'mail_list_id' => $this->mailListId,
                'imported' => $result['imported'],
                'skipped' => $result['skipped'],
                'total' => $result['total'],
            ]);
        } catch (\Throwable $e) {
            Log::error('ImportContactsJob failed', [
                'tenant_id' => $this->tenantId,
                'mail_list_id' => $this->mailListId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            try {
                Storage::disk($this->disk)->delete($this->storedPath);
            } catch (\Throwable) {
                // Best-effort cleanup.
            }

            if (! $alreadyOnTenant && tenancy()->initialized) {
                tenancy()->end();
            }
        }
    }
}
