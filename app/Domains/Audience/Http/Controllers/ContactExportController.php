<?php

declare(strict_types=1);

namespace App\Domains\Audience\Http\Controllers;

use App\Domains\Audience\Services\ContactService;
use App\Models\MailList;
use App\Support\PublicId;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactExportController extends Controller
{
    public function __construct(
        private readonly ContactService $service,
    ) {}

    /**
     * Export contacts as CSV download.
     */
    public function export(Request $request): StreamedResponse
    {
        $mailList = $request->filled('mail_list_id')
            ? PublicId::find(MailList::class, (string) $request->input('mail_list_id'))
            : null;
        $mailListId = $mailList?->id;
        $filename = 'contacts-' . date('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($mailListId): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Phone', 'Name', 'Email', 'Country Code', 'Status', 'Opt-in Status', 'Source', 'Created At']);

            \App\Models\Contact::query()
                ->when($mailListId, fn ($q) => $q->where('mail_list_id', $mailListId))
                ->cursor()
                ->each(function (\App\Models\Contact $contact) use ($handle): void {
                    fputcsv($handle, [
                        $contact->phone,
                        $contact->name,
                        $contact->email,
                        $contact->country_code,
                        $contact->status?->value ?? '',
                        $contact->opt_in_status?->value ?? '',
                        $contact->source,
                        $contact->created_at?->toDateTimeString(),
                    ]);
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
