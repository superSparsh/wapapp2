<?php

declare(strict_types=1);

namespace App\Domains\Account\Http\Controllers;

use App\Domains\Account\Http\Requests\DataExportRequest;
use App\Domains\Account\Http\Requests\ScheduleDataDeletionRequest;
use App\Domains\Account\Services\DataDeletionService;
use App\Http\Controllers\Controller;
use App\Models\DataDeletionSchedule;
use App\Models\DataExport;
use App\Models\User;
use App\Support\DataDeletionConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DataDeletionController extends Controller
{
    public function show(DataDeletionService $dataDeletionService): View
    {
        return view('profile.data-deletion', [
            'modules' => DataDeletionConfig::modules(),
            'dataAgeOptions' => DataDeletionConfig::dataAgeLabels(),
            'scheduleOptions' => DataDeletionConfig::scheduleLabels(),
            'exportRetentionDays' => DataDeletionConfig::exportRetentionDays(),
            'exports' => $dataDeletionService->exports(),
            'schedules' => $dataDeletionService->schedules(),
        ]);
    }

    public function export(DataExportRequest $request, DataDeletionService $dataDeletionService): RedirectResponse
    {
        $dataDeletionService->requestExport(
            dataAge: $request->validated('data_age'),
            modules: $request->validated('modules'),
            user: $this->ownerUser(),
        );

        return redirect()
            ->route('profile.data-deletion')
            ->with('status', 'Export started. It will appear in Available Exports shortly.');
    }

    public function schedule(ScheduleDataDeletionRequest $request, DataDeletionService $dataDeletionService): RedirectResponse
    {
        $dataDeletionService->scheduleDeletion(
            dataAge: $request->validated('data_age'),
            modules: $request->validated('modules'),
            scheduleDelay: $request->validated('schedule_delay'),
            exportBeforeDelete: $request->boolean('export_before_delete', true),
            user: $this->ownerUser(),
        );

        return redirect()
            ->route('profile.data-deletion')
            ->with('status', 'Data deletion scheduled.');
    }

    public function cancelSchedule(DataDeletionSchedule $schedule, DataDeletionService $dataDeletionService): RedirectResponse
    {
        $dataDeletionService->cancelSchedule($schedule);

        return redirect()
            ->route('profile.data-deletion')
            ->with('status', 'Scheduled deletion cancelled.');
    }

    public function download(DataExport $export, DataDeletionService $dataDeletionService): BinaryFileResponse|RedirectResponse
    {
        $path = $dataDeletionService->downloadExport($export);

        if (! $path) {
            return redirect()
                ->route('profile.data-deletion')
                ->withErrors(['export' => 'Export is not available for download.']);
        }

        return response()->download($path, 'wapapp-export-'.$export->uuid.'.json');
    }

    private function ownerUser(): User
    {
        $user = auth('web')->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
