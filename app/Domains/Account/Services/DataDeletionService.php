<?php

declare(strict_types=1);

namespace App\Domains\Account\Services;

use App\Enums\DataDeletionStatus;
use App\Enums\DataExportStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\DataDeletionSchedule;
use App\Models\DataExport;
use App\Models\Message;
use App\Models\User;
use App\Support\DataDeletionConfig;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class DataDeletionService
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
    ) {}

    /** @return Collection<int, DataExport> */
    public function exports(): Collection
    {
        $this->markExpiredExports();

        return DataExport::query()->latest('id')->get();
    }

    /** @return Collection<int, DataDeletionSchedule> */
    public function schedules(): Collection
    {
        return DataDeletionSchedule::query()->latest('id')->get();
    }

    /**
     * @param  list<string>  $modules
     */
    public function requestExport(string $dataAge, array $modules, User $user): DataExport
    {
        $export = DataExport::query()->create([
            'data_age' => $dataAge,
            'modules' => $modules,
            'status' => DataExportStatus::Processing,
            'requested_by' => $user->id,
        ]);

        $this->processExport($export);

        $this->activityLogService->log('data.export.requested', [
            'subject_type' => DataExport::class,
            'subject_id' => $export->id,
            'metadata' => ['modules' => $modules, 'data_age' => $dataAge],
        ]);

        return $export->fresh();
    }

    /**
     * @param  list<string>  $modules
     */
    public function scheduleDeletion(
        string $dataAge,
        array $modules,
        string $scheduleDelay,
        bool $exportBeforeDelete,
        User $user,
    ): DataDeletionSchedule {
        $scheduledFor = $this->resolveScheduleDate($scheduleDelay);

        $schedule = DataDeletionSchedule::query()->create([
            'data_age' => $dataAge,
            'modules' => $modules,
            'schedule_delay' => $scheduleDelay,
            'export_before_delete' => $exportBeforeDelete,
            'scheduled_for' => $scheduledFor,
            'status' => DataDeletionStatus::Scheduled,
            'requested_by' => $user->id,
        ]);

        if ($exportBeforeDelete) {
            $this->requestExport($dataAge, $modules, $user);
        }

        $this->activityLogService->log('data.deletion.scheduled', [
            'subject_type' => DataDeletionSchedule::class,
            'subject_id' => $schedule->id,
            'metadata' => [
                'modules' => $modules,
                'data_age' => $dataAge,
                'scheduled_for' => $scheduledFor->toIso8601String(),
            ],
        ]);

        return $schedule;
    }

    public function cancelSchedule(DataDeletionSchedule $schedule): void
    {
        abort_unless($schedule->status === DataDeletionStatus::Scheduled, 422);

        $schedule->update(['status' => DataDeletionStatus::Cancelled]);

        $this->activityLogService->log('data.deletion.cancelled', [
            'subject_type' => DataDeletionSchedule::class,
            'subject_id' => $schedule->id,
        ]);
    }

    public function downloadExport(DataExport $export): ?string
    {
        $this->markExpiredExports();
        $export->refresh();

        if ($export->status !== DataExportStatus::Completed || ! $export->file_path) {
            return null;
        }

        abort_unless(Storage::disk('local')->exists($export->file_path), 404);

        return Storage::disk('local')->path($export->file_path);
    }

    public function processDueSchedules(): int
    {
        $processed = 0;

        DataDeletionSchedule::query()
            ->where('status', DataDeletionStatus::Scheduled)
            ->where('scheduled_for', '<=', now())
            ->orderBy('id')
            ->each(function (DataDeletionSchedule $schedule) use (&$processed): void {
                $schedule->update(['status' => DataDeletionStatus::Processing]);

                $recordsDeleted = $this->deleteModuleData(
                    modules: $schedule->modules ?? [],
                    dataAge: $schedule->data_age,
                );

                $schedule->update([
                    'status' => DataDeletionStatus::Completed,
                    'records_deleted' => $recordsDeleted,
                    'completed_at' => now(),
                ]);

                $this->activityLogService->log('data.deletion.completed', [
                    'subject_type' => DataDeletionSchedule::class,
                    'subject_id' => $schedule->id,
                    'metadata' => [
                        'records_deleted' => $recordsDeleted,
                        'modules' => $schedule->modules,
                        'data_age' => $schedule->data_age,
                    ],
                ]);

                $processed++;
            });

        return $processed;
    }

    public function markExpiredExports(): void
    {
        DataExport::query()
            ->where('status', DataExportStatus::Completed)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => DataExportStatus::Expired]);
    }

    private function processExport(DataExport $export): void
    {
        $cutoff = $this->dataAgeCutoff($export->data_age);

        $payload = [
            'tenant_id' => tenant('id'),
            'exported_at' => now()->toIso8601String(),
            'data_age' => $export->data_age,
            'data_cutoff' => $cutoff->toIso8601String(),
            'modules' => collect($export->modules ?? [])->mapWithKeys(function (string $module) use ($cutoff): array {
                return [$module => $this->exportModuleData($module, $cutoff)];
            })->all(),
        ];

        $path = 'exports/'.tenant('id').'/'.$export->uuid.'.json';
        Storage::disk('local')->put($path, json_encode($payload, JSON_PRETTY_PRINT));
        $size = Storage::disk('local')->size($path);

        $export->update([
            'status' => DataExportStatus::Completed,
            'file_path' => $path,
            'file_size' => $size,
            'completed_at' => now(),
            'expires_at' => now()->addDays(DataDeletionConfig::exportRetentionDays()),
        ]);
    }

    /**
     * @param  list<string>  $modules
     */
    private function deleteModuleData(array $modules, string $dataAge): int
    {
        $cutoff = $this->dataAgeCutoff($dataAge);
        $deleted = 0;

        foreach ($modules as $module) {
            $deleted += $this->deleteRecordsForModule($module, $cutoff);
        }

        return $deleted;
    }

    private function dataAgeCutoff(string $dataAge): Carbon
    {
        return now()->subDays(DataDeletionConfig::dataAgeDays($dataAge));
    }

    private function resolveScheduleDate(string $scheduleDelay): Carbon
    {
        return now()->addDays(DataDeletionConfig::scheduleDays($scheduleDelay));
    }

    /** @return array<string, mixed> */
    private function exportModuleData(string $module, Carbon $cutoff): array
    {
        return match ($module) {
            'campaigns' => $this->exportCampaigns($cutoff),
            'audiences' => $this->exportAudiences($cutoff),
            'inbox' => $this->exportInbox($cutoff),
            default => [
                'count' => 0,
                'items' => [],
                'note' => 'Module exporter not implemented yet.',
            ],
        };
    }

    private function deleteRecordsForModule(string $module, Carbon $cutoff): int
    {
        return match ($module) {
            'campaigns' => $this->deleteCampaigns($cutoff),
            'audiences' => $this->deleteAudiences($cutoff),
            'inbox' => $this->deleteInbox($cutoff),
            default => 0,
        };
    }

    /** @return array<string, mixed> */
    private function exportCampaigns(Carbon $cutoff): array
    {
        $campaigns = Campaign::query()
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->limit(5000)
            ->get([
                'id',
                'name',
                'status',
                'audience_id',
                'whatsapp_line_id',
                'template_id',
                'scheduled_at',
                'started_at',
                'completed_at',
                'total_recipients',
                'total_delivered',
                'total_failed',
                'created_at',
            ]);

        return [
            'count' => $campaigns->count(),
            'items' => $campaigns->toArray(),
            'cutoff' => $cutoff->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function exportAudiences(Carbon $cutoff): array
    {
        $contacts = Contact::query()
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->limit(5000)
            ->get(['id', 'uuid', 'name', 'phone', 'email', 'created_at']);

        return [
            'count' => $contacts->count(),
            'items' => $contacts->toArray(),
            'cutoff' => $cutoff->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function exportInbox(Carbon $cutoff): array
    {
        $conversations = Conversation::query()
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->limit(5000)
            ->get(['id', 'uuid', 'contact_id', 'status', 'created_at']);

        return [
            'count' => $conversations->count(),
            'items' => $conversations->toArray(),
            'cutoff' => $cutoff->toIso8601String(),
        ];
    }

    private function deleteCampaigns(Carbon $cutoff): int
    {
        $campaignIds = Campaign::withTrashed()
            ->where('created_at', '<', $cutoff)
            ->pluck('id');

        if ($campaignIds->isEmpty()) {
            return 0;
        }

        CampaignRecipient::query()
            ->whereIn('campaign_id', $campaignIds)
            ->delete();

        return Campaign::withTrashed()
            ->whereIn('id', $campaignIds)
            ->forceDelete();
    }

    private function deleteAudiences(Carbon $cutoff): int
    {
        return Contact::query()
            ->where('created_at', '<', $cutoff)
            ->delete();
    }

    private function deleteInbox(Carbon $cutoff): int
    {
        $conversationIds = Conversation::query()
            ->where('created_at', '<', $cutoff)
            ->pluck('id');

        if ($conversationIds->isEmpty()) {
            return 0;
        }

        Message::query()
            ->whereIn('conversation_id', $conversationIds)
            ->delete();

        return Conversation::query()
            ->whereIn('id', $conversationIds)
            ->delete();
    }
}
