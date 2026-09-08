<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Services;

use App\Domains\LegacyMigration\DTO\LegacyCustomerSnapshot;
use App\Domains\LegacyMigration\DTO\MigrationOptions;
use App\Domains\LegacyMigration\Importers\AiBotImporter;
use App\Domains\LegacyMigration\Importers\BillingImporter;
use App\Domains\LegacyMigration\Importers\CampaignImporter;
use App\Domains\LegacyMigration\Importers\ChatbotFlowImporter;
use App\Domains\LegacyMigration\Importers\ContactImporter;
use App\Domains\LegacyMigration\Importers\DripCampaignImporter;
use App\Domains\LegacyMigration\Importers\InboxImporter;
use App\Domains\LegacyMigration\Importers\IntegrationImporter;
use App\Domains\LegacyMigration\Importers\InteractiveMessageImporter;
use App\Domains\LegacyMigration\Importers\LegacyImporter;
use App\Domains\LegacyMigration\Importers\MailListImporter;
use App\Domains\LegacyMigration\Importers\OwnerUserImporter;
use App\Domains\LegacyMigration\Importers\SignupFormImporter;
use App\Domains\LegacyMigration\Importers\TeamMemberImporter;
use App\Domains\LegacyMigration\Importers\TemplateImporter;
use App\Domains\LegacyMigration\Importers\TriggerVariableImporter;
use App\Domains\LegacyMigration\Importers\VariableImporter;
use App\Domains\LegacyMigration\Importers\WhatsappFlowImporter;
use App\Domains\LegacyMigration\Importers\WhatsappLineImporter;
use App\Domains\LegacyMigration\Support\LegacyConnection;
use App\Domains\LegacyMigration\Support\MigrationIdMap;
use App\Domains\LegacyMigration\Support\MigrationReport;
use App\Models\LegacyCustomerMigration;
use App\Models\Tenant;
use RuntimeException;
use Throwable;

class CustomerMigrationOrchestrator
{
    /** @var array<string, LegacyImporter> */
    private array $importers;

    public function __construct(
        private readonly LegacyConnection $legacy,
        private readonly LegacyCustomerResolver $resolver,
        private readonly TenantBootstrapper $bootstrapper,
        OwnerUserImporter $owner,
        WhatsappLineImporter $lines,
        MailListImporter $lists,
        ContactImporter $contacts,
        TemplateImporter $templates,
        InteractiveMessageImporter $interactiveMessages,
        VariableImporter $variables,
        SignupFormImporter $forms,
        TriggerVariableImporter $triggerTemplates,
        TeamMemberImporter $team,
        CampaignImporter $campaigns,
        ChatbotFlowImporter $chatbots,
        DripCampaignImporter $drips,
        WhatsappFlowImporter $whatsappFlows,
        AiBotImporter $ai,
        InboxImporter $inbox,
        BillingImporter $billing,
        IntegrationImporter $integrations,
    ) {
        $this->importers = [
            $owner->key() => $owner,
            $lines->key() => $lines,
            $lists->key() => $lists,
            $contacts->key() => $contacts,
            $templates->key() => $templates,
            $interactiveMessages->key() => $interactiveMessages,
            $variables->key() => $variables,
            $forms->key() => $forms,
            $triggerTemplates->key() => $triggerTemplates,
            $team->key() => $team,
            $campaigns->key() => $campaigns,
            $chatbots->key() => $chatbots,
            $drips->key() => $drips,
            $whatsappFlows->key() => $whatsappFlows,
            $ai->key() => $ai,
            $inbox->key() => $inbox,
            $billing->key() => $billing,
            $integrations->key() => $integrations,
        ];
    }

    /**
     * @return array{
     *     customer: LegacyCustomerSnapshot,
     *     tenant_id: ?string,
     *     created_tenant: bool,
     *     reused_reason: ?string,
     *     dry_run: bool,
     *     report: array<string, mixed>
     * }
     */
    public function migrate(string $identifier, MigrationOptions $options): array
    {
        $this->legacy->assertReady();

        $customer = $this->resolver->resolve($identifier);

        return $this->migrateSnapshot($customer, $options);
    }

    /**
     * @return array{
     *     customer: LegacyCustomerSnapshot,
     *     tenant_id: ?string,
     *     created_tenant: bool,
     *     reused_reason: ?string,
     *     dry_run: bool,
     *     report: array<string, mixed>
     * }
     */
    public function migratePilot(MigrationOptions $options): array
    {
        $this->legacy->assertReady();

        return $this->migrateSnapshot($this->resolver->resolvePilot(), $options);
    }

    /**
     * @return list<array{
     *     customer: LegacyCustomerSnapshot,
     *     tenant_id: ?string,
     *     created_tenant: bool,
     *     reused_reason: ?string,
     *     dry_run: bool,
     *     report: array<string, mixed>,
     *     error: ?string
     * }>
     */
    public function migrateAll(MigrationOptions $options, ?callable $onCustomer = null): array
    {
        $this->legacy->assertReady();

        $results = [];
        $candidates = $this->resolver->listCandidates(limit: 500);

        foreach ($candidates as $candidate) {
            if ($onCustomer !== null) {
                $onCustomer($candidate);
            }

            try {
                $result = $this->migrate((string) $candidate['id'], $options);
                $results[] = [...$result, 'error' => null];
            } catch (Throwable $exception) {
                $results[] = [
                    'customer' => $this->resolver->resolve((string) $candidate['id']),
                    'tenant_id' => null,
                    'created_tenant' => false,
                    'reused_reason' => null,
                    'dry_run' => $options->dryRun,
                    'report' => [],
                    'error' => $exception->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * @return array{
     *     customer: LegacyCustomerSnapshot,
     *     tenant_id: ?string,
     *     created_tenant: bool,
     *     reused_reason: ?string,
     *     dry_run: bool,
     *     report: array<string, mixed>
     * }
     */
    private function migrateSnapshot(LegacyCustomerSnapshot $customer, MigrationOptions $options): array
    {
        $report = new MigrationReport();
        $ids = new MigrationIdMap();

        $record = $this->beginRecord($customer);

        if ($record->status === 'completed' && ! $options->force && ! $options->dryRun) {
            throw new RuntimeException(
                "Legacy customer #{$customer->id} already migrated to tenant [{$record->tenant_id}]. Use --force to re-sync."
            );
        }

        if (is_array($record->report['id_map'] ?? null)) {
            foreach ($record->report['id_map'] as $entity => $map) {
                if (! is_array($map)) {
                    continue;
                }
                foreach ($map as $legacyId => $newId) {
                    $ids->put((string) $entity, $legacyId, $newId);
                }
            }
        }

        if ($options->dryRun) {
            $preview = [
                'customer_id' => $customer->id,
                'email' => $customer->email,
                'company' => $customer->displayName(),
                'counts' => $customer->counts,
                'modules' => $options->modules(),
            ];

            return [
                'customer' => $customer,
                'tenant_id' => $record->tenant_id,
                'created_tenant' => false,
                'reused_reason' => null,
                'dry_run' => true,
                'report' => [
                    'modules' => [],
                    'warnings' => [],
                    'errors' => [],
                    'totals' => ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0],
                    'preview' => $preview,
                ],
            ];
        }

        $boot = $this->bootstrapper->resolveTenant($customer, $options->force);
        /** @var Tenant $tenant */
        $tenant = $boot['tenant'];

        $record->forceFill([
            'tenant_id' => $tenant->id,
            'status' => 'running',
            'started_at' => $record->started_at ?? now(),
            'last_error' => null,
            'preview' => $customer->counts,
        ])->save();

        tenancy()->initialize($tenant);

        try {
            foreach ($options->modules() as $module) {
                $importer = $this->importers[$module] ?? null;
                if ($importer === null) {
                    $report->warn("Unknown module [{$module}] skipped.");

                    continue;
                }

                $importer->import($customer, $tenant, $ids, $report, false);
            }

            $payload = [
                ...$report->toArray(),
                'id_map' => $ids->all(),
            ];

            tenancy()->end();

            $record->forceFill([
                'status' => 'completed',
                'completed_at' => now(),
                'report' => $payload,
                'last_error' => null,
            ])->save();

            return [
                'customer' => $customer,
                'tenant_id' => $tenant->id,
                'created_tenant' => (bool) $boot['created'],
                'reused_reason' => $boot['reused_reason'],
                'dry_run' => false,
                'report' => $payload,
            ];
        } catch (Throwable $exception) {
            if (tenancy()->initialized) {
                tenancy()->end();
            }

            $payload = [
                ...$report->toArray(),
                'id_map' => $ids->all(),
            ];

            $record->forceFill([
                'status' => 'failed',
                'report' => $payload,
                'last_error' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }
    }

    private function beginRecord(LegacyCustomerSnapshot $customer): LegacyCustomerMigration
    {
        return tenancy()->central(function () use ($customer) {
            return LegacyCustomerMigration::query()->firstOrCreate(
                ['legacy_customer_id' => $customer->id],
                [
                    'legacy_customer_uid' => $customer->uid,
                    'legacy_email' => $customer->email,
                    'status' => 'pending',
                    'preview' => $customer->counts,
                ],
            );
        });
    }
}
