<?php

declare(strict_types=1);

namespace App\Domains\Audience\Http\Controllers;

use App\Domains\Audience\Services\ContactImportService;
use App\Models\MailList;
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
            ->get(['id', 'name']);

        return view('audience.subscribers-import', [
            'mailLists' => $mailLists,
            'mailListId' => $request->integer('list'),
        ]);
    }

    /**
     * Process CSV import.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:102400'],
            'mail_list_id' => ['nullable', 'integer', 'exists:mail_lists,id'],
        ]);

        $result = $this->importService->import(
            $request->file('file'),
            $request->integer('mail_list_id') ?: null,
        );

        return redirect()->route('audience.subscribers')
            ->with('status', "Import complete. {$result['imported']} imported, {$result['skipped']} skipped out of {$result['total']} total.");
    }
}
