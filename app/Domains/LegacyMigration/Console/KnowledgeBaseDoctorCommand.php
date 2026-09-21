<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Console;

use App\Domains\AiBot\Services\AiPythonClient;
use App\Domains\AiBot\Services\KnowledgeBaseProxyService;
use App\Domains\LegacyMigration\Support\LegacyConnection;
use App\Models\AiBot;
use App\Models\LegacyCustomerMigration;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Diagnose why Knowledge Base shows 0 after migrating chroma_data.
 */
class KnowledgeBaseDoctorCommand extends Command
{
    protected $signature = 'legacy:kb-doctor
                            {--tenant= : Tenant id (required unless only one migrated tenant)}
                            {--bot= : AiBot uuid or id (default: first / default bot)}';

    protected $description = 'Diagnose empty Knowledge Base (Chroma keys, AI service, legacy uid)';

    public function handle(LegacyConnection $legacy, AiPythonClient $python): int
    {
        $this->info('=== Knowledge Base doctor ===');
        $this->newLine();

        $pythonUrl = (string) config('ai.python_url');
        $enabled = (bool) config('ai.enabled', true);
        $this->line('PYTHON_AI_URL: '.($pythonUrl !== '' ? $pythonUrl : '(empty)'));
        $this->line('PYTHON_AI_ENABLED: '.($enabled ? 'true' : 'false'));

        if (! $enabled || $pythonUrl === '') {
            $this->error('AI service is disabled or PYTHON_AI_URL missing in .env');

            return self::FAILURE;
        }

        // Ping AI
        try {
            $ping = Http::timeout(5)->get(rtrim($pythonUrl, '/').'/docs');
            $this->line('AI /docs HTTP: '.$ping->status());
        } catch (Throwable $e) {
            $this->error('Cannot reach AI service: '.$e->getMessage());
            $this->comment('Start uvicorn / systemctl restart wapapp-ai');

            return self::FAILURE;
        }

        // List Chroma collections
        try {
            $cols = Http::timeout(15)->get(rtrim($pythonUrl, '/').'/debug/collections');
            if ($cols->successful()) {
                $data = $cols->json();
                $this->line('CHROMA_DB_PATH (AI): '.($data['chroma_path'] ?? '?'));
                $collections = is_array($data['collections'] ?? null) ? $data['collections'] : [];
                $this->line('Collections on disk: '.count($collections));
                foreach (array_slice($collections, 0, 30) as $col) {
                    $name = is_array($col) ? ($col['name'] ?? '?') : (string) $col;
                    $count = is_array($col) ? ($col['count'] ?? '?') : '?';
                    $this->line("  - {$name}  count={$count}");
                }
                if (count($collections) === 0) {
                    $this->error('chroma_data is EMPTY — you likely copied ai_chatbot code only, not chroma_data.');
                }
            } else {
                $this->warn('GET /debug/collections failed HTTP '.$cols->status().' — deploy latest ai-service main.py');
            }
        } catch (Throwable $e) {
            $this->warn('debug/collections: '.$e->getMessage());
        }

        $this->newLine();

        $tenant = $this->resolveTenant();
        if ($tenant === null) {
            return self::FAILURE;
        }

        $legacyCustomerId = data_get($tenant->settings, 'legacy_customer_id');
        $this->line("Tenant: {$tenant->id}");
        $this->line('settings.legacy_customer_id: '.(filled($legacyCustomerId) ? (string) $legacyCustomerId : '(MISSING)'));

        if (! filled($legacyCustomerId)) {
            $mig = LegacyCustomerMigration::query()
                ->where('tenant_id', $tenant->id)
                ->where('status', 'completed')
                ->orderByDesc('id')
                ->first();
            if ($mig) {
                $this->warn("Found migration record customer #{$mig->legacy_customer_id} but NOT on tenant.settings — fixing…");
                $settings = $tenant->settings ?? [];
                $settings['legacy_customer_id'] = (int) $mig->legacy_customer_id;
                $tenant->forceFill(['settings' => $settings])->save();
                $legacyCustomerId = (int) $mig->legacy_customer_id;
                $this->info("Wrote legacy_customer_id={$legacyCustomerId} onto tenant.settings");
            } else {
                $this->error('No legacy_customer_id — Chroma client_id will be tenant slug (wrong for old data).');
            }
        }

        tenancy()->initialize($tenant);

        try {
            /** @var KnowledgeBaseProxyService $proxy */
            $proxy = app(KnowledgeBaseProxyService::class);

            $botOpt = $this->option('bot');
            $bot = filled($botOpt)
                ? $proxy->resolveBot((string) $botOpt)
                : $proxy->resolveBot(null);

            if ($bot === null) {
                $this->error('No AiBot in this tenant.');

                return self::FAILURE;
            }

            $this->line("Bot: id={$bot->id} name=\"{$bot->name}\" uuid={$bot->uuid}");
            $this->line('legacy_bot_id: '.($bot->legacy_bot_id ?? '(null)'));
            $this->line('legacy_bot_uid: '.(filled($bot->legacy_bot_uid) ? (string) $bot->legacy_bot_uid : '(MISSING)'));

            if (! filled($bot->legacy_bot_uid)) {
                $this->error('legacy_bot_uid missing — run: php artisan legacy:backfill-ai-bot-uids');
                try {
                    $legacy->assertReady();
                    if (filled($legacyCustomerId) && $legacy->tableExists('ai_bots')) {
                        $rows = $legacy->db()->table('ai_bots')
                            ->where('customer_id', (int) $legacyCustomerId)
                            ->get(['id', 'uid', 'name']);
                        $this->line('Legacy ai_bots for this customer:');
                        foreach ($rows as $row) {
                            $this->line("  #{$row->id} uid={$row->uid} name=\"{$row->name}\"");
                        }
                    }
                } catch (Throwable $e) {
                    $this->warn('Legacy DB: '.$e->getMessage());
                }
            }

            $clientId = $proxy->chromaClientId();
            $botKey = $proxy->chromaBotId($bot);
            $this->newLine();
            $this->info("Chroma lookup keys Laravel will send:");
            $this->line("  client_id = {$clientId}");
            $this->line('  bot_id    = '.($botKey ?? '(none)'));
            $expected = $botKey
                ? 'bot_'.substr(md5("client_{$clientId}_bot_{$botKey}"), 0, 12)
                : 'client_'.substr(md5($clientId), 0, 8);
            $this->line("  expected collection = {$expected}");

            $this->newLine();
            $this->info('Calling AI service…');
            try {
                $list = $proxy->list(botId: $bot->uuid, limit: 5, offset: 0);
                $listData = is_array($list['data'] ?? null) ? $list['data'] : $list;
                $total = (int) ($listData['total_documents'] ?? 0);
                $coll = $listData['collection_name'] ?? '?';
                $this->line("list total_documents={$total} collection_name={$coll}");
                if ($total === 0) {
                    $this->error('AI returned 0 documents for these keys.');
                    $this->comment('Either wrong keys, or chroma_data does not contain that collection.');
                } else {
                    $this->info("OK — {$total} chunks found. Refresh the UI.");
                }
            } catch (Throwable $e) {
                $this->error('list() failed: '.$e->getMessage());
            }
        } finally {
            tenancy()->end();
        }

        return self::SUCCESS;
    }

    private function resolveTenant(): ?Tenant
    {
        $id = $this->option('tenant');
        if (filled($id)) {
            $tenant = Tenant::query()->find((string) $id);
            if ($tenant === null) {
                $this->error("Tenant [{$id}] not found.");

                return null;
            }

            return $tenant;
        }

        $fromMig = LegacyCustomerMigration::query()
            ->where('status', 'completed')
            ->orderByDesc('id')
            ->get()
            ->unique('tenant_id');

        if ($fromMig->count() === 1) {
            return Tenant::query()->find((string) $fromMig->first()->tenant_id);
        }

        $withLegacy = Tenant::query()->get()->filter(
            fn (Tenant $t) => filled(data_get($t->settings, 'legacy_customer_id'))
        );

        if ($withLegacy->count() === 1) {
            return $withLegacy->first();
        }

        $this->error('Pass --tenant=YOUR_TENANT_ID');
        if ($fromMig->isNotEmpty()) {
            $this->line('Migrated tenants:');
            foreach ($fromMig as $row) {
                $this->line("  {$row->tenant_id}  legacy_customer_id={$row->legacy_customer_id}");
            }
        }

        return null;
    }
}
