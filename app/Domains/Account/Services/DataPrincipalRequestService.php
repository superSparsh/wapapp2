<?php

declare(strict_types=1);

namespace App\Domains\Account\Services;

use App\Enums\DataPrincipalRequestStatus;
use App\Enums\DataPrincipalRequestType;
use App\Models\Admin;
use App\Models\DataPrincipalRequest;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class DataPrincipalRequestService
{
    /**
     * @param  array{
     *   requester_name: string,
     *   requester_email: string,
     *   requester_phone?: ?string,
     *   request_type: string|DataPrincipalRequestType,
     *   description: string,
     *   nominee_name?: ?string,
     *   nominee_email?: ?string,
     *   nominee_phone?: ?string,
     *   nominee_relation?: ?string,
     *   tenant_id?: ?string,
     *   meta?: array<string, mixed>
     * }  $payload
     */
    public function submit(array $payload, ?User $user = null): DataPrincipalRequest
    {
        $type = $payload['request_type'] instanceof DataPrincipalRequestType
            ? $payload['request_type']
            : DataPrincipalRequestType::from((string) $payload['request_type']);

        $slaDays = max(1, (int) config('legal.rights.sla_days', config('legal.grievance.response_days', 7)));

        return DataPrincipalRequest::query()->create([
            'tenant_id' => $payload['tenant_id'] ?? (tenant('id') ? (string) tenant('id') : null),
            'user_id' => $user?->id,
            'requester_name' => $payload['requester_name'],
            'requester_email' => strtolower($payload['requester_email']),
            'requester_phone' => $payload['requester_phone'] ?? null,
            'request_type' => $type,
            'description' => $payload['description'],
            'nominee_name' => $payload['nominee_name'] ?? null,
            'nominee_email' => isset($payload['nominee_email']) ? strtolower((string) $payload['nominee_email']) : null,
            'nominee_phone' => $payload['nominee_phone'] ?? null,
            'nominee_relation' => $payload['nominee_relation'] ?? null,
            'status' => DataPrincipalRequestStatus::Open,
            'due_at' => now()->addDays($slaDays),
            'meta' => $payload['meta'] ?? null,
        ]);
    }

    /** @return Collection<int, DataPrincipalRequest> */
    public function forEmail(string $email): Collection
    {
        return DataPrincipalRequest::query()
            ->where('requester_email', strtolower($email))
            ->orderByDesc('created_at')
            ->get();
    }

    public function paginateForAdmin(int $perPage = 20): LengthAwarePaginator
    {
        return DataPrincipalRequest::query()
            ->with('handledByAdmin')
            ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'in_progress' THEN 1 ELSE 2 END")
            ->orderBy('due_at')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function updateStatus(
        DataPrincipalRequest $request,
        DataPrincipalRequestStatus $status,
        Admin $admin,
        ?string $adminNote = null,
    ): DataPrincipalRequest {
        $request->status = $status;
        $request->handled_by_admin_id = $admin->id;
        if ($adminNote !== null) {
            $request->admin_note = $adminNote;
        }
        if ($status->isTerminal()) {
            $request->resolved_at = now();
        }
        $request->save();

        return $request->refresh();
    }

    public function tableReady(): bool
    {
        $connection = (string) config('tenancy.database.central_connection', config('database.default'));

        return Schema::connection($connection)->hasTable('data_principal_requests');
    }
}
