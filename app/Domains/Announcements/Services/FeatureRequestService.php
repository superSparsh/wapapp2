<?php

declare(strict_types=1);

namespace App\Domains\Announcements\Services;

use App\Domains\Alerts\Services\OperationalWhatsAppSender;
use App\Models\Announcement;
use App\Models\AnnouncementFeatureRequest;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class FeatureRequestService
{
    public function __construct(
        private readonly OperationalWhatsAppSender $whatsAppSender,
    ) {}

    public function listActiveForTenant(?string $tenantId = null): LengthAwarePaginator
    {
        $tenantId ??= (string) (tenant('id') ?? '');

        $paginator = Announcement::query()
            ->currentlyActive()
            ->latest('id')
            ->paginate(10);

        $requestedIds = AnnouncementFeatureRequest::query()
            ->where('tenant_id', $tenantId)
            ->pluck('announcement_id')
            ->all();

        $paginator->getCollection()->transform(function (Announcement $announcement) use ($requestedIds): Announcement {
            $announcement->setAttribute('already_requested', in_array($announcement->id, $requestedIds, true));

            return $announcement;
        });

        return $paginator;
    }

    /**
     * @return array{message: string, status: int, already?: bool}
     */
    public function requestActivation(Announcement $announcement, User $user, string $tenantId): array
    {
        if (! $announcement->is_active) {
            return ['message' => 'This announcement is not active.', 'status' => 422];
        }

        $existing = AnnouncementFeatureRequest::query()
            ->where('announcement_id', $announcement->id)
            ->where('tenant_id', $tenantId)
            ->first();

        if ($existing !== null) {
            return [
                'message' => 'You have already submitted a request for this announcement.',
                'status' => 200,
                'already' => true,
            ];
        }

        $planName = $this->resolvePlanName();

        $request = AnnouncementFeatureRequest::query()->create([
            'announcement_id' => $announcement->id,
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'customer_email' => (string) $user->email,
            'customer_name' => (string) $user->name,
            'plan_name' => $planName,
            'is_acknowledged' => false,
            'is_viewed' => false,
        ]);

        $this->notifyDevelopers($announcement, $user, $tenantId, $planName);

        Log::info('Feature activation requested', [
            'request_id' => $request->id,
            'announcement_id' => $announcement->id,
            'tenant_id' => $tenantId,
        ]);

        return ['message' => 'Request submitted successfully!', 'status' => 200];
    }

    private function resolvePlanName(): string
    {
        $subscription = Subscription::query()->orderByDesc('id')->first();
        if ($subscription?->plan_id) {
            $plan = Plan::query()->find($subscription->plan_id);

            return (string) ($plan?->name ?? 'N/A');
        }

        return 'N/A';
    }

    private function notifyDevelopers(Announcement $announcement, User $user, string $tenantId, string $planName): void
    {
        $template = (string) config(
            'operational-alerts.feature_request.whatsapp_template',
            'customer_new_feature_request_wapapp'
        );
        $numbers = (array) config('operational-alerts.developer_whatsapp_numbers', []);

        if ($template === '' || $numbers === []) {
            Log::info('Feature request WhatsApp skipped: missing template or developer numbers');

            return;
        }

        $params = [
            'feature_name' => (string) $announcement->title,
            'customer_subscription' => $planName,
            'customer_name' => (string) $user->name,
            'customer_id' => $tenantId,
            'customer_email' => (string) $user->email,
        ];

        foreach ($numbers as $number) {
            $this->whatsAppSender->sendTemplate((string) $number, $template, $params);
        }
    }
}
