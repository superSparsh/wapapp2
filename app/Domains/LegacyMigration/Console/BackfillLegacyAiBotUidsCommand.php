<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Console;

use App\Domains\LegacyMigration\Support\LegacyConnection;
use App\Models\AiBot;
use App\Models\LegacyCustomerMigration;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Backfill ai_bots.legacy_bot_uid from legacy ai_bots.uid so Chroma KB collections resolve.
 *
 * Legacy Python calls use: /knowledge_base/{customer_id}?bot_id={ai_bots.uid}
 */
class BackfillLegacyAiBotUidsCommand extends Command
{
    protected $signature = 'legacy:backfill-ai-bot-uids
                            {--tenant= : Only this tenant id}
                            {--dry-run : Show matches without writing}
                            {--force : Overwrite legacy_bot_uid even if already set}';

    protected $description = 'Copy legacy ai_bots.uid into tenant ai_bots.legacy_bot_uid (fixes empty Knowledge Base after chroma_data copy)';

    public function handle(LegacyConnection $legacy): int
    {
        try {
            $legacy->assertReady();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            $this->line('Set LEGACY_DB_* in .env to the old WapApp MySQL database.');

            return self::FAILURE;
        }

        if (! $legacy->tableExists('ai_bots')) {
            $this->error('Legacy table ai_bots not found.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $onlyTenant = $this->option('tenant');

        $targets = $this->resolveTargets(is_string($onlyTenant) ? $onlyTenant : null);
        if ($targets->isEmpty()) {
            $this->warn('No migrated tenants with legacy_customer_id found.');

            return self::SUCCESS;
        }

        $updated = 0;
        $skipped = 0;
        $unmatched = 0;

        foreach ($targets as $target) {
            $tenantId = (string) $target['tenant_id'];
            $legacyCustomerId = (int) $target['legacy_customer_id'];
            $idMap = is_array($target['id_map'] ?? null) ? $target['id_map'] : [];

            $tenant = Tenant::query()->find($tenantId);
            if ($tenant === null) {
                $this->warn("Tenant [{$tenantId}] missing — skip.");

                continue;
            }

            $legacyBots = $legacy->db()->table('ai_bots')
                ->where('customer_id', $legacyCustomerId)
                ->orderBy('id')
                ->get(['id', 'uid', 'name']);

            if ($legacyBots->isEmpty()) {
                $this->line("{$tenantId}: no legacy ai_bots for customer #{$legacyCustomerId}");

                continue;
            }

            tenancy()->initialize($tenant);

            try {
                $newBots = AiBot::query()->orderBy('id')->get();

                foreach ($legacyBots as $legacyBot) {
                    $legacyUid = filled($legacyBot->uid ?? null) ? (string) $legacyBot->uid : null;
                    if ($legacyUid === null) {
                        $this->warn("{$tenantId}: legacy bot #{$legacyBot->id} has empty uid — skip.");
                        $skipped++;

                        continue;
                    }

                    $match = $this->matchNewBot($newBots, $legacyBot, $idMap);
                    if ($match === null) {
                        $this->warn(
                            "{$tenantId}: no match for legacy bot #{$legacyBot->id} \"{$legacyBot->name}\" uid={$legacyUid}"
                        );
                        $unmatched++;

                        continue;
                    }

                    if (filled($match->legacy_bot_uid) && ! $force && (string) $match->legacy_bot_uid === $legacyUid) {
                        $skipped++;

                        continue;
                    }

                    if (filled($match->legacy_bot_uid) && ! $force) {
                        $this->line(
                            "{$tenantId}: bot \"{$match->name}\" already has legacy_bot_uid={$match->legacy_bot_uid} (use --force)"
                        );
                        $skipped++;

                        continue;
                    }

                    $this->info(
                        ($dryRun ? '[dry-run] ' : '').
                        "{$tenantId}: \"{$match->name}\" ← uid={$legacyUid} (legacy #{$legacyBot->id})"
                    );

                    if (! $dryRun) {
                        $match->forceFill([
                            'legacy_bot_id' => (int) $legacyBot->id,
                            'legacy_bot_uid' => $legacyUid,
                        ])->save();
                    }

                    $updated++;
                }
            } finally {
                tenancy()->end();
            }
        }

        $this->newLine();
        $this->info($dryRun ? 'Dry run complete.' : 'Backfill complete.');
        $this->line("Updated: {$updated}");
        $this->line("Skipped: {$skipped}");
        $this->line("Unmatched: {$unmatched}");

        if (! $dryRun && $updated > 0) {
            $this->comment('Refresh AI Assistant → Knowledge Base. Ensure CHROMA_DB_PATH points at legacy chroma_data.');
        }

        return $unmatched > 0 && $updated === 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return Collection<int, array{tenant_id: string, legacy_customer_id: int, id_map: array<string, mixed>}>
     */
    private function resolveTargets(?string $onlyTenant): Collection
    {
        $fromMigrations = LegacyCustomerMigration::query()
            ->where('status', 'completed')
            ->when($onlyTenant, fn ($q) => $q->where('tenant_id', $onlyTenant))
            ->orderByDesc('id')
            ->get()
            ->unique('tenant_id')
            ->map(fn (LegacyCustomerMigration $row) => [
                'tenant_id' => (string) $row->tenant_id,
                'legacy_customer_id' => (int) $row->legacy_customer_id,
                'id_map' => is_array($row->report['id_map']['ai_bot'] ?? null)
                    ? $row->report['id_map']['ai_bot']
                    : [],
            ]);

        if ($fromMigrations->isNotEmpty()) {
            return $fromMigrations->values();
        }

        // Fallback: tenants.settings.legacy_customer_id
        return Tenant::query()
            ->when($onlyTenant, fn ($q) => $q->where('id', $onlyTenant))
            ->get()
            ->filter(fn (Tenant $t) => filled(data_get($t->settings, 'legacy_customer_id')))
            ->map(fn (Tenant $t) => [
                'tenant_id' => (string) $t->id,
                'legacy_customer_id' => (int) data_get($t->settings, 'legacy_customer_id'),
                'id_map' => [],
            ])
            ->values();
    }

    /**
     * @param  Collection<int, AiBot>  $newBots
     * @param  array<string, int|string>  $idMap  legacy_id => new_id
     */
    private function matchNewBot(Collection $newBots, object $legacyBot, array $idMap): ?AiBot
    {
        $legacyId = (string) $legacyBot->id;

        if (isset($idMap[$legacyId])) {
            $newId = (int) $idMap[$legacyId];
            $byId = $newBots->firstWhere('id', $newId);
            if ($byId instanceof AiBot) {
                return $byId;
            }
        }

        $name = trim((string) ($legacyBot->name ?? ''));
        if ($name !== '') {
            $byName = $newBots->first(
                fn (AiBot $bot) => strcasecmp(trim((string) $bot->name), $name) === 0
            );
            if ($byName instanceof AiBot) {
                return $byName;
            }
        }

        if ($newBots->count() === 1) {
            return $newBots->first();
        }

        return null;
    }
}
