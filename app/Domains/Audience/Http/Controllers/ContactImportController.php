<?php

declare(strict_types=1);

namespace App\Domains\Audience\Http\Controllers;

use App\Domains\Audience\Services\ContactImportService;
use App\Models\MailList;
use App\Support\PublicId;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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
        ]);
    }

    /**
     * Process CSV import.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:102400'],
            'mail_list_id' => PublicId::uuidExistsRules(MailList::class),
        ]);

        $mailList = PublicId::find(MailList::class, $validated['mail_list_id'] ?? null);

        $result = $this->importService->import(
            $request->file('file'),
            $mailList?->id,
        );

        return redirect()->route('audience.subscribers', array_filter(['list' => $mailList?->uuid]))
            ->with('status', "Import complete. {$result['imported']} imported, {$result['skipped']} skipped out of {$result['total']} total.");
    }
}
