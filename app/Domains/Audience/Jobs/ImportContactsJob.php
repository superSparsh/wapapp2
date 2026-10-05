<?php

declare(strict_types=1);

namespace App\Domains\Audience\Jobs;

use App\Domains\Audience\Services\ContactImportService;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Queued CSV import (legacy parity for 40k+ rows).
 */
class ImportContactsJob implements ShouldQueue
{
    use Queueable;

    /** One attempt: CSV side-effects are not safely idempotent mid-file. */
    public int $tries = 1;

    public int $timeout = 7200;

    public bool $failOnTimeout = true;

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
                throw new \RuntimeException('ImportContactsJob: tenant not found: '.$this->tenantId);
            }

            tenancy()->initialize($tenant);
        }

        $imported = false;

        try {
            $disk = Storage::disk($this->disk);

            if (! $disk->exists($this->storedPath)) {
                throw new \RuntimeException(
                    'ImportContactsJob: CSV missing on disk "'.$this->disk.'" at '.$this->storedPath
                    .' (worker may not share web upload storage).'
                );
            }

            $absolute = $disk->path($this->storedPath);
            $result = $importService->importFromPath(
                $absolute,
                $this->mailListId,
                $this->forceSendOptIn,
            );

            $imported = true;

            Log::info('ImportContactsJob completed', [
                'tenant_id' => $this->tenantId,
                'mail_list_id' => $this->mailListId,
                'imported' => $result['imported'],
                'skipped' => $result['skipped'],
                'total' => $result['total'],
            ]);
        } catch (Throwable $e) {
            Log::error('ImportContactsJob failed', [
                'tenant_id' => $this->tenantId,
                'mail_list_id' => $this->mailListId,
                'path' => $this->storedPath,
                'disk' => $this->disk,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            // Only delete after a successful import so a timeout/crash can be diagnosed
            // and manually replayed from the same path when storage is shared.
            if ($imported) {
                try {
                    Storage::disk($this->disk)->delete($this->storedPath);
                } catch (Throwable) {
                    // Best-effort cleanup.
                }
            }

            if (! $alreadyOnTenant && tenancy()->initialized) {
                tenancy()->end();
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        $root = $exception;
        while ($root instanceof MaxAttemptsExceededException && $root->getPrevious() instanceof Throwable) {
            $root = $root->getPrevious();
        }

        Log::error('ImportContactsJob permanently failed', [
            'tenant_id' => $this->tenantId,
            'mail_list_id' => $this->mailListId,
            'path' => $this->storedPath,
            'disk' => $this->disk,
            'exception' => $exception !== null ? $exception::class : null,
            'message' => $exception?->getMessage(),
            'root_exception' => $root !== null ? $root::class : null,
            'root_message' => $root?->getMessage(),
        ]);
    }
}
