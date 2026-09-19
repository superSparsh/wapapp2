<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Importers;

use App\Domains\LegacyMigration\DTO\LegacyCustomerSnapshot;
use App\Domains\LegacyMigration\Support\LegacyConnection;
use App\Domains\LegacyMigration\Support\MigrationIdMap;
use App\Domains\LegacyMigration\Support\MigrationReport;
use App\Enums\AiProvider;
use App\Enums\BusinessInfoContentType;
use App\Enums\EmbeddingStatus;
use App\Models\AiBot;
use App\Models\AiBusinessInfo;
use App\Models\AiProviderKey;
use App\Models\AiSetting;
use App\Models\Tenant;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Imports tenant AI settings that AiBotImporter does not cover:
 * provider API keys, global ai_response toggle, and knowledge-base text.
 */
final class AiSettingsImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyConnection $legacy,
    ) {}

    public function key(): string
    {
        return 'ai_settings';
    }

    public function import(
        LegacyCustomerSnapshot $customer,
        Tenant $tenant,
        MigrationIdMap $ids,
        MigrationReport $report,
        bool $dryRun = false,
    ): void {
        $this->importProviderKeys($customer, $report, $dryRun);
        $this->importCustomerOpenAiFallback($customer, $report, $dryRun);
        $this->importAutoResponseToggle($customer, $report, $dryRun);
        $this->importBusinessInformation($customer, $report, $dryRun);
    }

    private function importProviderKeys(
        LegacyCustomerSnapshot $customer,
        MigrationReport $report,
        bool $dryRun,
    ): void {
        if (! $this->legacy->tableExists('provider_api_keys')) {
            return;
        }

        $rows = $this->legacy->db()->table('provider_api_keys')
            ->where('customer_id', $customer->id)
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $plainKey = $this->decryptApiKey((string) ($row->api_key ?? ''));
            if ($plainKey === null) {
                $report->warn(sprintf(
                    'Skipped provider_api_keys#%d: could not decrypt api_key for customer_id=%d.',
                    (int) $row->id,
                    $customer->id,
                ));
                $report->bump($this->key(), 'skipped');

                continue;
            }

            $provider = $this->mapProvider($row->provider ?? 'openai');
            $existing = AiProviderKey::query()
                ->where('provider', $provider)
                ->orderByDesc('is_active')
                ->orderByDesc('id')
                ->first();

            if ($dryRun) {
                $report->bump($this->key(), $existing ? 'updated' : 'created');

                continue;
            }

            $attributes = [
                'provider' => $provider,
                'api_key' => $plainKey,
                'chat_model' => filled($row->chat_model ?? null) ? (string) $row->chat_model : null,
                'embedding_model' => filled($row->embedding_model ?? null) ? (string) $row->embedding_model : null,
                'embedding_dimensions' => isset($row->embedding_dimensions) ? (int) $row->embedding_dimensions : null,
                'is_active' => (bool) ($row->is_active ?? true),
                'is_validated' => (bool) ($row->is_validated ?? false),
            ];

            if ($existing !== null) {
                $existing->forceFill($attributes)->save();
                $report->bump($this->key(), 'updated');
            } else {
                AiProviderKey::query()->create($attributes);
                $report->bump($this->key(), 'created');
            }
        }
    }

    private function importCustomerOpenAiFallback(
        LegacyCustomerSnapshot $customer,
        MigrationReport $report,
        bool $dryRun,
    ): void {
        if (! $this->legacy->hasColumn('customers', 'openai_api_key')) {
            return;
        }

        // Prefer provider_api_keys when present.
        if (AiProviderKey::query()->where('provider', AiProvider::OpenAI)->exists()) {
            return;
        }

        $raw = $this->legacy->db()->table('customers')
            ->where('id', $customer->id)
            ->value('openai_api_key');

        $plainKey = $this->decryptApiKey((string) ($raw ?? ''));
        if ($plainKey === null) {
            return;
        }

        if ($dryRun) {
            $report->bump($this->key(), 'created');

            return;
        }

        AiProviderKey::query()->create([
            'provider' => AiProvider::OpenAI,
            'api_key' => $plainKey,
            'chat_model' => 'gpt-4o-mini',
            'embedding_model' => 'text-embedding-3-small',
            'is_active' => true,
            'is_validated' => false,
        ]);
        $report->bump($this->key(), 'created');
    }

    private function importAutoResponseToggle(
        LegacyCustomerSnapshot $customer,
        MigrationReport $report,
        bool $dryRun,
    ): void {
        if (! $this->legacy->hasColumn('customers', 'ai_response')) {
            return;
        }

        $enabled = (bool) $this->legacy->db()->table('customers')
            ->where('id', $customer->id)
            ->value('ai_response');

        if ($dryRun) {
            $report->bump($this->key(), 'updated');

            return;
        }

        AiSetting::set('ai_auto_response_enabled', $enabled);
        $report->bump($this->key(), 'updated');
    }

    private function importBusinessInformation(
        LegacyCustomerSnapshot $customer,
        MigrationReport $report,
        bool $dryRun,
    ): void {
        $blobs = [];

        if ($this->legacy->hasColumn('customers', 'business_information')) {
            $customerBlob = $this->legacy->db()->table('customers')
                ->where('id', $customer->id)
                ->value('business_information');
            if (filled($customerBlob)) {
                $blobs[] = ['title' => 'Migrated business information', 'raw' => (string) $customerBlob];
            }
        }

        if ($this->legacy->tableExists('line_ai_settings')
            && $this->legacy->hasColumn('line_ai_settings', 'business_information')
        ) {
            $lineRows = $this->legacy->db()->table('line_ai_settings')
                ->where('customer_id', $customer->id)
                ->whereNotNull('business_information')
                ->orderBy('id')
                ->get(['id', 'business_information']);

            foreach ($lineRows as $index => $row) {
                if (! filled($row->business_information)) {
                    continue;
                }
                $blobs[] = [
                    'title' => 'Migrated line AI knowledge #'.($index + 1),
                    'raw' => (string) $row->business_information,
                ];
            }
        }

        // Bot-level KB text (already copied onto AiBot.business_information by AiBotImporter).
        foreach (AiBot::query()->orderBy('id')->get(['id', 'name', 'business_information']) as $bot) {
            if (! filled($bot->business_information)) {
                continue;
            }
            $blobs[] = [
                'title' => 'Migrated from bot: '.$bot->name,
                'raw' => (string) $bot->business_information,
                'bot_id' => $bot->id,
            ];
        }

        if ($blobs === []) {
            return;
        }

        $defaultBot = $this->resolveKnowledgeBot($dryRun);
        if ($defaultBot === null && ! $dryRun) {
            $report->warn('No AI bot available to attach migrated knowledge base entries.');

            return;
        }

        $seen = [];
        foreach ($blobs as $blob) {
            $content = $this->unwrapBusinessInformation($blob['raw']);
            if ($content === null || $content === '') {
                continue;
            }

            $hash = md5($content);
            if (isset($seen[$hash])) {
                continue;
            }
            $seen[$hash] = true;

            $botId = $blob['bot_id'] ?? $defaultBot?->id;
            if ($botId === null) {
                if ($dryRun) {
                    $report->bump($this->key(), 'created');
                }

                continue;
            }

            $existing = AiBusinessInfo::query()
                ->where('ai_bot_id', $botId)
                ->where('title', $blob['title'])
                ->first();

            if ($dryRun) {
                $report->bump($this->key(), $existing ? 'updated' : 'created');

                continue;
            }

            $attributes = [
                'ai_bot_id' => $botId,
                'title' => $blob['title'],
                'content_type' => BusinessInfoContentType::Text,
                'content' => $content,
                'file_path' => null,
                'file_name' => null,
                'embedding_status' => EmbeddingStatus::Pending,
                'embedding_id' => null,
            ];

            if ($existing !== null) {
                $existing->forceFill($attributes)->save();
                $report->bump($this->key(), 'updated');
            } else {
                AiBusinessInfo::query()->create($attributes);
                $report->bump($this->key(), 'created');
            }
        }
    }

    private function resolveKnowledgeBot(bool $dryRun): ?AiBot
    {
        if ($dryRun) {
            return AiBot::query()->orderByDesc('is_default')->orderBy('id')->first();
        }

        $bot = AiBot::query()->orderByDesc('is_default')->orderBy('id')->first();
        if ($bot instanceof AiBot) {
            return $bot;
        }

        return AiBot::query()->create([
            'name' => 'Migrated Knowledge Base',
            'type' => 'assistant',
            'system_prompt' => null,
            'provider' => AiProvider::OpenAI,
            'chat_model' => 'gpt-4o-mini',
            'embedding_model' => 'text-embedding-3-small',
            'temperature' => 0.7,
            'business_information' => null,
            'status' => 'active',
            'is_default' => true,
            'whatsapp_line_id' => null,
        ]);
    }

    private function unwrapBusinessInformation(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            if (isset($decoded['business_information']) && is_scalar($decoded['business_information'])) {
                $inner = trim((string) $decoded['business_information']);

                return $inner !== '' ? $inner : null;
            }

            // Nested structures — keep a readable dump rather than drop.
            if ($decoded !== []) {
                return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: $raw;
            }
        }

        return $raw;
    }

    private function decryptApiKey(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        try {
            $decrypted = Crypt::decryptString($raw);
            $decrypted = trim((string) $decrypted);

            return $decrypted !== '' ? $decrypted : null;
        } catch (Throwable) {
            // Legacy customers.openai_api_key / query-builder inserts may be plaintext.
            if (str_starts_with($raw, 'sk-') || (strlen($raw) >= 20 && ! str_starts_with($raw, 'eyJ'))) {
                return $raw;
            }

            return null;
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
