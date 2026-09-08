<?php

declare(strict_types=1);

namespace App\Domains\WhatsappFlow\Services;

use App\Domains\WhatsappFlow\Support\WhatsappFlowMetaJsonConverter;
use App\Enums\WhatsappFlowStatus;
use App\Models\WhatsappFlow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WhatsappFlowService
{
    public function __construct(
        private readonly WhatsappFlowCamsService $camsService,
        private readonly WhatsappFlowAssetService $assetService,
        private readonly WhatsappFlowExchangeRegistryService $registryService,
    ) {}

    /**
     * @param  array{name: string, whatsapp_line_id?: int|null, categories?: array<int, string>|null, on_submit_action?: string|null, on_submit_webhook_url?: string|null}  $data
     */
    public function create(array $data): WhatsappFlow
    {
        $maxFlows = (int) config('whatsapp-flows.max_flows_per_tenant', 100);
        $currentCount = WhatsappFlow::query()->count();

        if ($currentCount >= $maxFlows) {
            throw ValidationException::withMessages([
                'name' => 'Maximum number of flows reached for this account.',
            ]);
        }

        return DB::transaction(function () use ($data): WhatsappFlow {
            $categories = $data['categories'] ?? ['OTHER'];

            $flow = WhatsappFlow::query()->create([
                'name' => $data['name'],
                'status' => WhatsappFlowStatus::Draft,
                'categories' => $categories,
                'whatsapp_line_id' => $data['whatsapp_line_id'] ?? null,
                'on_submit_action' => $data['on_submit_action'] ?? 'none',
                'on_submit_webhook_url' => $data['on_submit_webhook_url'] ?? null,
                'created_by' => auth('team')->id(),
            ]);

            $flow->update([
                'cust_space_id' => $this->camsService->resolveCustSpaceId($flow),
            ]);
            $flow->refresh();

            $metaFlowId = $this->camsService->createRemote($flow, $categories);

            if ($metaFlowId !== null) {
                $flow->update(['meta_flow_id' => $metaFlowId]);
            }

            return $flow->refresh();
        });
    }

    /**
     * @param  array{name?: string, status?: string, whatsapp_line_id?: int|null, categories?: array<int, string>|null, on_submit_action?: string|null, on_submit_webhook_url?: string|null}  $data
     */
    public function update(WhatsappFlow $flow, array $data): WhatsappFlow
    {
        if ($flow->isActive()) {
            throw ValidationException::withMessages([
                'name' => 'Published flows cannot be edited. Duplicate or archive first.',
            ]);
        }

        return DB::transaction(function () use ($flow, $data): WhatsappFlow {
            $fillable = [];

            foreach (['name'] as $field) {
                if (isset($data[$field])) {
                    $fillable[$field] = $data[$field];
                }
            }

            foreach (['whatsapp_line_id'] as $fk) {
                if (array_key_exists($fk, $data)) {
                    $fillable[$fk] = $data[$fk];
                }
            }

            if (isset($data['categories'])) {
                $fillable['categories'] = $data['categories'];
            }

            if (isset($data['on_submit_action'])) {
                $fillable['on_submit_action'] = $data['on_submit_action'];
            }

            if (array_key_exists('on_submit_webhook_url', $data)) {
                $fillable['on_submit_webhook_url'] = $data['on_submit_webhook_url'];
            }

            if ($fillable !== []) {
                $flow->update($fillable);
            }

            return $flow->refresh();
        });
    }

    public function delete(WhatsappFlow $flow): void
    {
        DB::transaction(function () use ($flow): void {
            if ($flow->isActive()) {
                throw ValidationException::withMessages([
                    'flow' => 'Active flows must be archived before deletion.',
                ]);
            }

            if ($flow->exchange_token) {
                $this->registryService->remove($flow->exchange_token);
            }

            $this->camsService->deleteRemote($flow);
            $flow->delete();
        });
    }

    public function publish(WhatsappFlow $flow): WhatsappFlow
    {
        if ($flow->screenCount() === 0) {
            throw ValidationException::withMessages([
                'flow' => 'Add at least one screen before publishing.',
            ]);
        }

        if ($flow->draft_synced_at === null) {
            throw ValidationException::withMessages([
                'flow' => 'Save the flow as draft before publishing.',
            ]);
        }

        $token = (string) Str::uuid();
        $endpoint = url('/v1/flow-exchange/'.$token);

        if ($this->camsService->isConfigured() && filled($flow->meta_flow_id)) {
            if (! $this->camsService->publishRemote($flow)) {
                throw ValidationException::withMessages([
                    'flow' => 'Remote publish failed. Check CAMS configuration and try again.',
                ]);
            }
        }

        $tenant = tenant();

        if ($tenant !== null) {
            $this->registryService->register($token, $tenant, $flow);
        }

        $flow->update([
            'status' => WhatsappFlowStatus::Active,
            'exchange_token' => $token,
            'data_exchange_endpoint' => $endpoint,
            'published_at' => now(),
        ]);

        return $flow->refresh();
    }

    public function archive(WhatsappFlow $flow): WhatsappFlow
    {
        if ($flow->exchange_token) {
            $this->registryService->remove($flow->exchange_token);
        }

        $this->camsService->deprecateRemote($flow);

        $flow->update([
            'status' => WhatsappFlowStatus::Archived,
            'exchange_token' => null,
            'data_exchange_endpoint' => null,
        ]);

        return $flow->refresh();
    }

    public function duplicate(WhatsappFlow $flow): WhatsappFlow
    {
        return DB::transaction(function () use ($flow): WhatsappFlow {
            $clone = $flow->replicate([
                'uuid',
                'meta_flow_id',
                'exchange_token',
                'data_exchange_endpoint',
                'published_at',
                'draft_synced_at',
                'json_asset_path',
            ]);
            $clone->name = $flow->name.' (Copy)';
            $clone->status = WhatsappFlowStatus::Draft;
            $clone->save();

            $metaFlowId = $this->camsService->createRemote($clone, (array) ($clone->categories ?? ['OTHER']));

            if ($metaFlowId !== null) {
                $clone->update(['meta_flow_id' => $metaFlowId]);
            }

            if (is_array($flow->flow_json) && $flow->flow_json !== []) {
                $this->saveFlowJson($clone, $flow->flow_json);
            }

            return $clone->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $flowJson
     */
    public function saveFlowJson(WhatsappFlow $flow, array $flowJson): WhatsappFlow
    {
        if ($flow->isActive()) {
            throw ValidationException::withMessages([
                'flow_json' => 'Published flows cannot be modified.',
            ]);
        }

        $this->assertFlowLimits($flowJson);

        $metaJson = WhatsappFlowMetaJsonConverter::convert($flowJson);
        $assetPath = $this->assetService->write($flow, $metaJson);

        $flow->update([
            'flow_json' => $flowJson,
            'meta_json' => $metaJson,
            'json_asset_path' => $assetPath,
        ]);

        $synced = $this->camsService->syncJsonAsset($flow->refresh());

        $flow->update([
            'draft_synced_at' => $synced || ! $this->camsService->isConfigured() ? now() : null,
        ]);

        return $flow->refresh();
    }

    public function isNameAvailable(string $name, ?int $exceptId = null): bool
    {
        return ! WhatsappFlow::query()
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->where('name', $name)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $flowJson
     */
    private function assertFlowLimits(array $flowJson): void
    {
        $screens = $flowJson['screens'] ?? [];
        $maxScreens = (int) config('whatsapp-flows.max_screens_per_flow', 8);
        $maxFields = (int) config('whatsapp-flows.max_fields_per_screen', 50);

        if (! is_array($screens)) {
            throw ValidationException::withMessages(['flow_json' => 'Invalid flow structure.']);
        }

        if (count($screens) > $maxScreens) {
            throw ValidationException::withMessages([
                'flow_json' => "Maximum {$maxScreens} screens allowed per flow.",
            ]);
        }

        foreach ($screens as $screen) {
            $fieldCount = count($screen['fields'] ?? []);

            if ($fieldCount > $maxFields) {
                throw ValidationException::withMessages([
                    'flow_json' => "Maximum {$maxFields} fields allowed per screen.",
                ]);
            }
        }
    }
}
