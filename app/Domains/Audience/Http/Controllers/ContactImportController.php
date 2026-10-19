<?php

declare(strict_types=1);

namespace App\Domains\Audience\Http\Controllers;

use App\Domains\Audience\Jobs\ImportContactsJob;
use App\Domains\Audience\Services\ContactImportService;
use App\Models\MailList;
use App\Support\OciWorkload;
use App\Support\PublicId;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ContactImportController extends Controller
{
    public function __construct(
        private readonly ContactImportService $importService,
    ) {}

    /**
     * Show import page.
     */
    public function show(Request $request): View
    {
        $mailLists = MailList::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'uuid', 'name']);

        $mailList = $request->filled('list')
            ? PublicId::find(MailList::class, (string) $request->input('list'))
            : null;

        return view('audience.subscribers-import', [
            'mailLists' => $mailLists,
            'mailListId' => $mailList?->uuid,
            'mailListName' => $mailList?->name,
        ]);
    }

    /**
     * Queue CSV import (legacy parity for large lists).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:102400'],
            'mail_list_id' => PublicId::uuidExistsRules(MailList::class, nullable: false),
            'send_opt_in_message' => ['nullable', 'string', 'in:yes,no'],
        ]);

        $mailList = PublicId::find(MailList::class, $validated['mail_list_id'] ?? null);
        abort_if($mailList === null, 422, 'Target list is required.');

        $tenantId = tenant('id');
        abort_if(! is_string($tenantId) || $tenantId === '', 500, 'Tenant context is missing.');

        $file = $request->file('file');
        abort_if($file === null, 422, 'CSV file is required.');

        // Validate headers early so users get an immediate error (before the job).
        try {
            $this->assertCsvHeaders($file->getRealPath() ?: '');
        } catch (\RuntimeException $e) {
            return redirect()->back()->withErrors(['file' => $e->getMessage()])->withInput();
        }

        $storedPath = $file->storeAs(
            'imports/'.$tenantId,
            'import-'.now()->format('YmdHis').'-'.uniqid('', true).'.csv',
            'local',
        );

        $forceSendOptIn = ($validated['send_opt_in_message'] ?? 'no') === 'yes';

        $absolute = Storage::disk('local')->path($storedPath);
        $estimatedRows = OciWorkload::estimateCsvRows($absolute);
        $queue = OciWorkload::queueForImport($estimatedRows);

        try {
            ImportContactsJob::dispatch(
                $tenantId,
                $storedPath,
                (int) $mailList->id,
                $forceSendOptIn,
                'local',
            )->onQueue($queue);
        } catch (\Throwable $e) {
            // Sync queue rethrows job failures; surface them instead of a blank 500.
            $result = ContactImportService::pullResult($tenantId, (int) $mailList->id);
            $message = is_array($result) && isset($result['message'])
                ? (string) $result['message']
                : $e->getMessage();
            $errors = is_array($result['errors'] ?? null) ? $result['errors'] : ['Import failed: '.$message];

            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['file' => $message])
                ->with('error', $message)
                ->with('import_errors', $errors);
        }

        // Sync queue (tests / local): job already finished - give accurate counts + errors.
        if (config('queue.default') === 'sync') {
            $result = ContactImportService::pullResult($tenantId, (int) $mailList->id) ?? [];
            $imported = (int) ($result['imported'] ?? 0);
            $skipped = (int) ($result['skipped'] ?? 0);
            $errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];
            $failed = (bool) ($result['failed'] ?? false);

            $status = $failed
                ? 'Import failed.'.(isset($result['message']) ? ' '.$result['message'] : '')
                : "Import complete: {$imported} imported, {$skipped} skipped.";

            $redirect = redirect()
                ->route('audience.subscribers', array_filter(['list' => $mailList->uuid]))
                ->with('status', $status);

            if ($errors !== []) {
                $redirect->with('import_errors', $errors);
            }

            if ($failed || $errors !== []) {
                $redirect->with('error', $failed
                    ? ($result['message'] ?? 'Import failed.')
                    : "{$skipped} row(s) could not be imported. See details below.");
            }

            return $redirect;
        }

        return redirect()
            ->route('audience.subscribers', array_filter(['list' => $mailList->uuid]))
            ->with('status', 'Import queued. Large lists are processed in the background - refresh this page in a few minutes to see results and any row errors.');
    }

    private function assertCsvHeaders(string $path): void
    {
        if ($path === '' || ! is_readable($path)) {
            throw new \RuntimeException('Unable to read the uploaded CSV file.');
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open the uploaded CSV file.');
        }

        try {
            $headers = fgetcsv($handle);
            if ($headers === false || $headers === [null] || $headers === []) {
                throw new \RuntimeException('CSV file is empty or has no header row.');
            }

            $headers = array_map(
                static fn ($h): string => strtolower(trim((string) $h)),
                $headers,
            );

            $normalized = array_map(
                static fn (string $h): string => in_array($h, ['whatsapp_number', 'phone'], true) ? 'phone_number' : $h,
                $headers,
            );

            $missing = array_values(array_diff(ContactImportService::REQUIRED_HEADERS, $normalized));
            if ($missing !== []) {
                throw new \RuntimeException(
                    'Import missing required header field(s): '.implode(', ', $missing)
                    .'. Download Sample.csv and keep the exact header row.',
                );
            }
        } finally {
            fclose($handle);
        }
    }
}
