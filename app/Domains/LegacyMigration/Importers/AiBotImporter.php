<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Importers;

use App\Domains\LegacyMigration\DTO\LegacyCustomerSnapshot;
use App\Domains\LegacyMigration\Support\LegacyConnection;
use App\Domains\LegacyMigration\Support\MigrationIdMap;
use App\Domains\LegacyMigration\Support\MigrationReport;
use App\Enums\AiProvider;
use App\Models\AiBot;
use App\Models\Tenant;

final class AiBotImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyConnection $legacy,
    ) {}

    public function key(): string
    {
        return 'ai';
    }

    public function import(
        LegacyCustomerSnapshot $customer,
        Tenant $tenant,
        MigrationIdMap $ids,
        MigrationReport $report,
        bool $dryRun = false,
    ): void {
        if (! $this->legacy->tableExists('ai_bots')) {
            return;
        }

        $rows = $this->legacy->db()->table('ai_bots')
            ->where('customer_id', $customer->id)
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $legacyId = (int) $row->id;
            $name = trim((string) ($row->name ?? 'AI Bot'));
            $existingId = $ids->getInt('ai_bot', $legacyId);
            $existing = $existingId
                ? AiBot::query()->find($existingId)
                : AiBot::query()->where('name', $name)->first();

            if ($dryRun) {
                $report->bump($this->key(), $existing ? 'updated' : 'created');

                continue;
            }

            $lineId = $row->new_contact_id ? $ids->getInt('line', (int) $row->new_contact_id) : null;

            $attributes = [
                'name' => $name,
                'type' => filled($row->type ?? null) ? (string) $row->type : 'assistant',
                'system_prompt' => $row->system_prompt,
                'provider' => $this->mapProvider($row->provider ?? null),
                'chat_model' => filled($row->chat_model ?? null)
                    ? (string) $row->chat_model
                    : (filled($row->model_name ?? null) ? (string) $row->model_name : 'gpt-4o-mini'),
                'embedding_model' => filled($row->embedding_model ?? null)
                    ? (string) $row->embedding_model
                    : 'text-embedding-3-small',
                'temperature' => isset($row->temperature) ? (float) $row->temperature : 0.7,
                'business_information' => $row->business_information,
                'status' => filled($row->status ?? null) ? (string) $row->status : 'active',
                'is_default' => (bool) ($row->is_default ?? false),
                'whatsapp_line_id' => $lineId,
            ];

            if ($existing !== null) {
                $existing->forceFill($attributes)->save();
                $bot = $existing;
                $report->bump($this->key(), 'updated');
            } else {
                $bot = AiBot::query()->create($attributes);
                $report->bump($this->key(), 'created');
            }

            $ids->put('ai_bot', $legacyId, $bot->id);
        }
    }

    private function mapProvider(mixed $provider): AiProvider
    {
        $value = strtolower((string) $provider);

        foreach (AiProvider::cases() as $case) {
            if (str_contains($value, strtolower($case->value)) || str_contains($value, strtolower($case->name))) {
                return $case;
            }
        }

        return AiProvider::OpenAI;
    }
}
