<?php

declare(strict_types=1);

namespace App\Domains\Team\Http\Controllers;

use App\Domains\Team\Http\Requests\ImportTeamMembersRequest;
use App\Domains\Team\Services\TeamAccessService;
use App\Domains\Team\Services\TeamImportService;
use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeamImportController extends Controller
{
    public function form(TeamAccessService $accessService): View
    {
        $accessService->manager();

        return view('manager.team.import', [
            'permissionLabels' => config('team.permissions', []),
        ]);
    }

    public function store(
        ImportTeamMembersRequest $request,
        TeamAccessService $accessService,
        TeamImportService $importService,
    ) {
        $manager = $accessService->manager();
        $report = $importService->importForManager($manager, $request->file('file'));

        return redirect()
            ->route('manager.team.index')
            ->with('import_report', $report)
            ->with('status', "{$report['created']} members imported, {$report['skipped']} skipped.");
    }

    public function sample(TeamImportService $importService): StreamedResponse
    {
        return response()->streamDownload(
            fn () => print($importService->sampleCsv()),
            'team-members-sample.csv',
            ['Content-Type' => 'text/csv'],
        );
    }
}
