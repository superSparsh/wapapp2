<?php

declare(strict_types=1);

namespace App\Domains\Audience\Http\Controllers;

use App\Domains\Audience\Http\Requests\Blacklist\StoreBlacklistRequest;
use App\Domains\Audience\Models\Blacklist;
use App\Domains\Audience\Services\BlacklistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class BlacklistController extends Controller
{
    public function __construct(
        private readonly BlacklistService $service,
    ) {}

    public function index(Request $request): View
    {
        return view('audience.blacklist', [
            'entries' => $this->service->index($request->get('search')),
        ]);
    }

    /**
     * Add to blacklist.
     */
    public function store(StoreBlacklistRequest $request): RedirectResponse
    {
        $this->service->store($request->validated());

        return redirect()->route('audience.blacklist')
            ->with('status', 'Entry added to blacklist.');
    }

    /**
     * Remove from blacklist.
     */
    public function destroy(Blacklist $blacklist): RedirectResponse
    {
        $this->service->destroy($blacklist);

        return redirect()->route('audience.blacklist')
            ->with('status', 'Entry removed from blacklist.');
    }

    /**
     * Import from CSV.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:102400'],
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $headers = fgetcsv($handle);
        $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $headers ?: []);
        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($headers) !== count($row)) {
                continue;
            }
            $rows[] = array_combine($headers, $row) ?: [];
        }
        fclose($handle);

        $count = $this->service->importRows($rows);

        return redirect()->route('audience.blacklist')
            ->with('status', "{$count} entries imported to blacklist.");
    }
}
