<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Support\AdminListQuery;
use App\Http\Controllers\Controller;
use App\Models\CloudBillUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CloudBillController extends Controller
{
    public function index(Request $request): View
    {
        $parsed = AdminListQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'period', 'amount', 'filename'],
            defaultSort: 'id',
            defaultDirection: 'desc',
        );

        $query = CloudBillUpload::query();
        AdminListQuery::applySearch($query, $parsed['q'], ['filename', 'period', 'status', 'currency']);
        AdminListQuery::applySort(
            $query,
            $parsed['sort'],
            $parsed['direction'],
            [
                'id' => 'id',
                'period' => 'period',
                'amount' => 'amount',
                'filename' => 'filename',
            ],
            'id',
        );

        return view('admin.cloud-bills.index', [
            'bills' => $query->paginate(20)->withQueryString(),
            'filters' => $parsed,
            'sortOptions' => [
                ['value' => 'id', 'label' => 'Newest first', 'direction' => 'desc'],
                ['value' => 'period', 'label' => 'Period', 'direction' => 'desc'],
                ['value' => 'amount', 'label' => 'Amount', 'direction' => 'desc'],
                ['value' => 'filename', 'label' => 'Filename A–Z', 'direction' => 'asc'],
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.cloud-bills.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'period' => ['nullable', 'string', 'max:64'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        $file = $request->file('file');
        $path = $file->store('cloud-bills', 'local');

        CloudBillUpload::query()->create([
            'filename' => $file->getClientOriginalName(),
            'path' => $path,
            'period' => $validated['period'] ?? null,
            'amount' => $validated['amount'] ?? null,
            'currency' => strtoupper((string) ($validated['currency'] ?? 'USD')),
            'exchange_rate' => $validated['exchange_rate'] ?? null,
            'status' => 'uploaded',
            'uploaded_by' => auth('admin')->id(),
        ]);

        return redirect()->route('admin.cloud-bills.index')->with('status', 'Cloud bill uploaded.');
    }

    public function show(CloudBillUpload $bill): View
    {
        return view('admin.cloud-bills.show', compact('bill'));
    }

    public function updateRate(Request $request, CloudBillUpload $bill): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'max:32'],
        ]);

        $bill->fill([
            'amount' => $validated['amount'] ?? $bill->amount,
            'currency' => isset($validated['currency']) ? strtoupper($validated['currency']) : $bill->currency,
            'exchange_rate' => $validated['exchange_rate'] ?? $bill->exchange_rate,
            'status' => $validated['status'] ?? $bill->status,
        ])->save();

        return back()->with('status', 'Bill details updated.');
    }

    public function download(CloudBillUpload $bill): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($bill->path), 404);

        return Storage::disk('local')->download($bill->path, $bill->filename);
    }

    public function destroy(CloudBillUpload $bill): RedirectResponse
    {
        Storage::disk('local')->delete($bill->path);
        $bill->delete();

        return redirect()->route('admin.cloud-bills.index')->with('status', 'Bill deleted.');
    }
}
